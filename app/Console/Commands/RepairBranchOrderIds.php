<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RepairBranchOrderIds extends Command
{
    protected $signature = 'orders:repair-ids
                            {--branch= : Branch ID, for example 5}
                            {--connection=oracle : Source database connection}
                            {--mapping= : Mapping JSON path}
                            {--regenerate : Ignore an existing mapping and create a new plan}
                            {--apply : Apply the prepared mapping; without this option the command is dry-run}';

    protected $description = 'Prepare or apply branch-scoped IDs for legacy orders and their references';

    private $connection;
    private $availableTables = [];
    private $reservedIds = [];

    private $childTables = [
        'order_details' => 'order_id',
        'pos_order_additional_dtl' => 'order_id',
        'kitchen_order_status_logs' => 'order_id',
    ];

    /**
     * These tables contain order_id references. Missing tables are ignored so
     * the command can run on installations with a smaller schema.
     */
    private $orderReferenceTables = [
        'cash_back_histories',
        'delivery_histories',
        'dm_reviews',
        'expenses',
        'notifications',
        'offline_payments',
        'order_cancel_reasons',
        'order_delivery_histories',
        'order_payments',
        'order_transactions',
        'order_whatsapp_msg_logs',
        'refunds',
        'reviews',
        'subscription_logs',
    ];

    public function handle()
    {
        $branchId = (int) $this->option('branch');

        if ($branchId < 5 || $branchId > 16) {
            $this->error('A branch ID between 5 and 16 is required.');
            return 1;
        }

        $lockPath = storage_path('app/order-id-repair/branch-' . $branchId . '.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        $lockHandle = fopen($lockPath, 'c');

        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if (is_resource($lockHandle)) {
                fclose($lockHandle);
            }
            $this->error("Another repair command is already running for branch [$branchId].");
            return 1;
        }

        try {
            return $this->runRepair($branchId);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function runRepair($branchId)
    {
        $this->connection = DB::connection($this->option('connection'));
        $this->availableTables = $this->loadAvailableTables();

        foreach (array_merge(['orders', 'shift_sessions'], array_keys($this->childTables)) as $table) {
            if (!$this->hasTable($table)) {
                $this->error("Required table [$table] was not found.");
                return 1;
            }
        }

        if (!$this->hasTable('global_id_reservations')) {
            $this->error('Run the global_id_reservations migration before repairing IDs.');
            return 1;
        }

        $mappingPath = $this->option('mapping')
            ?: storage_path('app/order-id-repair/branch-' . $branchId . '.json');

        $plan = File::exists($mappingPath) && !$this->option('regenerate')
            ? $this->readPlan($mappingPath, $branchId)
            : $this->buildPlan($branchId, $mappingPath);

        $this->printPlan($plan, $mappingPath);

        if (!$this->option('apply')) {
            $this->line('Dry-run complete. No database rows were changed.');
            $this->line('Review the mapping file, then run the same command with --apply.');
            return 0;
        }

        if (!$this->confirm('Apply this mapping to the branch database?')) {
            $this->warn('Cancelled. No database rows were changed.');
            return 0;
        }

        try {
            $this->connection->transaction(function () use ($plan, $branchId) {
                $this->applyPlan($plan, $branchId);
            });
        } catch (\Throwable $exception) {
            $this->error('Repair failed and the database transaction was rolled back.');
            $this->error($exception->getMessage());
            return 1;
        }

        $this->info('Repair completed successfully.');
        $this->line('All repaired orders and related rows are marked is_pushed=N where that column exists.');

        return 0;
    }

    private function buildPlan($branchId, $mappingPath)
    {
        $plan = [
            'version' => 1,
            'branch_id' => $branchId,
            'created_at' => now()->toIso8601String(),
            'orders' => [],
            'sessions' => [],
        ];

        foreach ($this->connection->table('orders')->orderBy('id')->get() as $order) {
            $oldId = (string) $order->id;
            $plan['orders'][$oldId] = $this->isBranchId($oldId, $branchId)
                ? $oldId
                : $this->generateId('orders', 'id', $branchId);
        }

        if ($this->hasTable('shift_sessions')) {
            $sessions = $this->connection->table('shift_sessions')
                ->where('branch_id', $branchId)
                ->orderBy('session_id')
                ->get();

            foreach ($sessions as $session) {
                $oldId = (string) $session->session_id;
                $plan['sessions'][$oldId] = $this->isBranchId($oldId, $branchId)
                    ? $oldId
                    : $this->generateId('shift_sessions', 'session_id', $branchId);
            }
        }

        File::ensureDirectoryExists(dirname($mappingPath));
        File::put($mappingPath, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $plan;
    }

    private function readPlan($mappingPath, $branchId)
    {
        $plan = json_decode(File::get($mappingPath), true);

        if (!is_array($plan) || (int) ($plan['branch_id'] ?? 0) !== $branchId) {
            throw new \RuntimeException('Mapping file does not belong to the requested branch.');
        }

        if (!isset($plan['orders']) || !isset($plan['sessions'])) {
            throw new \RuntimeException('Mapping file is incomplete. Generate a new dry-run plan.');
        }

        return $plan;
    }

    private function applyPlan(array $plan, $branchId)
    {
        $orderMap = $plan['orders'];
        $sessionMap = $plan['sessions'];

        foreach ($orderMap as $oldId => $newId) {
            if ($oldId !== $newId) {
                $this->reserveId($newId, $branchId, 'orders');
            }
        }

        foreach ($sessionMap as $oldId => $newId) {
            if ($oldId !== $newId) {
                $this->reserveId($newId, $branchId, 'shift_sessions');
            }
        }

        $this->assertTargetsAreFree($orderMap, 'orders', 'id');
        $this->assertTargetsAreFree($sessionMap, 'shift_sessions', 'session_id');

        // Insert new session rows first, then update order session references.
        foreach ($sessionMap as $oldId => $newId) {
            if ($oldId === $newId) {
                continue;
            }

            $session = $this->connection->table('shift_sessions')
                ->where('session_id', $oldId)
                ->first();

            if (!$session) {
                throw new \RuntimeException("Shift session [$oldId] was not found.");
            }

            $data = (array) $session;
            $data['session_id'] = $newId;
            $this->setNotPushed($data);
            $this->connection->table('shift_sessions')->insert($data);
        }

        // Insert new order parents before changing foreign keys.
        foreach ($orderMap as $oldId => $newId) {
            $order = $this->connection->table('orders')->where('id', $oldId)->first();

            if (!$order) {
                throw new \RuntimeException("Order [$oldId] was not found.");
            }

            $data = (array) $order;
            $data['id'] = $newId;
            $this->replaceSessionReferences($data, $sessionMap);
            $this->setNotPushed($data);

            if ($oldId !== $newId) {
                $this->connection->table('orders')->insert($data);
            } else {
                $this->connection->table('orders')->where('id', $oldId)->update($data);
            }
        }

        // Copy the child rows with new primary IDs and the new order_id.
        foreach ($this->childTables as $table => $foreignKey) {
            foreach ($orderMap as $oldOrderId => $newOrderId) {
                foreach ($this->connection->table($table)->where($foreignKey, $oldOrderId)->get() as $child) {
                    $data = (array) $child;
                    $data['id'] = $this->generateId($table, 'id', $branchId, true);
                    $data[$foreignKey] = $newOrderId;
                    $this->connection->table($table)->insert($data);
                    $this->connection->table($table)->where('id', $child->id)->delete();
                }
            }
        }

        // Update order_id in known reference tables. Their own primary IDs stay
        // unchanged because they are not part of the order sync payload.
        foreach ($this->existingOrderReferenceTables() as $table) {
            foreach ($orderMap as $oldOrderId => $newOrderId) {
                if ($oldOrderId !== $newOrderId) {
                    $this->connection->table($table)
                        ->where('order_id', $oldOrderId)
                        ->update(['order_id' => $newOrderId]);
                }
            }
        }

        // Update session references after new parents exist.
        foreach ($sessionMap as $oldSessionId => $newSessionId) {
            if ($oldSessionId === $newSessionId) {
                continue;
            }

            $this->connection->table('orders')
                ->where('session_id', $oldSessionId)
                ->update(['session_id' => $newSessionId]);

            $this->connection->table('orders')
                ->where('payment_user_session_id', $oldSessionId)
                ->update(['payment_user_session_id' => $newSessionId]);

            $this->connection->table('shift_sessions')
                ->where('session_id', $oldSessionId)
                ->delete();
        }

        // Delete old order parents only after all child references were moved.
        foreach ($orderMap as $oldOrderId => $newOrderId) {
            if ($oldOrderId !== $newOrderId) {
                $this->connection->table('orders')->where('id', $oldOrderId)->delete();
            }
        }

        // Ensure existing/current IDs are also sent again after the reset.
        if ($this->hasTableColumn('orders', 'is_pushed')) {
            $this->connection->table('orders')->update(['is_pushed' => 'N']);
        }
        if ($this->hasTableColumn('shift_sessions', 'is_pushed')) {
            $this->connection->table('shift_sessions')
                ->where('branch_id', $branchId)
                ->update(['is_pushed' => 'N']);
        }
    }

    private function replaceSessionReferences(array &$data, array $sessionMap)
    {
        foreach (['session_id', 'payment_user_session_id'] as $column) {
            if (isset($data[$column]) && $data[$column] !== null) {
                $key = (string) $data[$column];
                if (isset($sessionMap[$key])) {
                    $data[$column] = $sessionMap[$key];
                }
            }
        }
    }

    private function setNotPushed(array &$data)
    {
        if (array_key_exists('is_pushed', $data)) {
            $data['is_pushed'] = 'N';
        }
    }

    private function assertTargetsAreFree(array $map, $table, $column)
    {
        foreach ($map as $oldId => $newId) {
            if ($oldId === $newId) {
                continue;
            }

            if ($this->connection->table($table)->where($column, $newId)->exists()) {
                throw new \RuntimeException("Target ID [$newId] already exists in [$table]. Regenerate the plan.");
            }
        }
    }

    private function generateId($table, $column, $branchId, $reserve = false)
    {
        $branchPart = str_pad((string) $branchId, 2, '0', STR_PAD_LEFT);

        do {
            $id = '7' . $branchPart . str_pad((string) random_int(0, 9999999999999999), 16, '0', STR_PAD_LEFT);
        } while (isset($this->reservedIds[$table][$id])
            || $this->connection->table($table)->where($column, $id)->exists()
            || $this->connection->table('global_id_reservations')->where('global_id', $id)->exists());

        if (!isset($this->reservedIds[$table])) {
            $this->reservedIds[$table] = [];
        }
        $this->reservedIds[$table][$id] = true;

        if ($reserve) {
            $this->reserveId($id, $branchId, $table);
        }

        return $id;
    }

    private function reserveId($id, $branchId, $scope)
    {
        try {
            $this->connection->table('global_id_reservations')->insert([
                'global_id' => $id,
                'branch_id' => $branchId,
                'scope' => $scope,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                "ID [$id] is already reserved. Use --regenerate to create a new mapping.",
                0,
                $exception
            );
        }
    }

    private function isBranchId($id, $branchId)
    {
        return strlen((string) $id) === 19
            && substr((string) $id, 0, 3) === '7' . str_pad((string) $branchId, 2, '0', STR_PAD_LEFT)
            && ctype_digit((string) $id);
    }

    private function loadAvailableTables()
    {
        $tables = $this->connection->select(
            "SELECT LOWER(table_name) AS table_name FROM user_tables"
        );

        $available = [];
        foreach ($tables as $table) {
            $available[] = strtolower($table->table_name);
        }

        return array_flip($available);
    }

    private function hasTable($table)
    {
        return isset($this->availableTables[strtolower($table)]);
    }

    private function hasTableColumn($table, $column)
    {
        $rows = $this->connection->select(
            'SELECT COUNT(*) AS row_count FROM user_tab_columns WHERE table_name = ? AND column_name = ?',
            [strtoupper($table), strtoupper($column)]
        );

        return !empty($rows) && (int) $rows[0]->row_count > 0;
    }

    private function existingOrderReferenceTables()
    {
        $tables = [];

        foreach ($this->orderReferenceTables as $table) {
            if ($this->hasTable($table) && $this->hasTableColumn($table, 'order_id')) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    private function printPlan(array $plan, $mappingPath)
    {
        $orderChanges = 0;
        foreach ($plan['orders'] as $oldId => $newId) {
            if ($oldId !== $newId) {
                $orderChanges++;
            }
        }

        $sessionChanges = 0;
        foreach ($plan['sessions'] as $oldId => $newId) {
            if ($oldId !== $newId) {
                $sessionChanges++;
            }
        }

        $this->table(['Item', 'Count'], [
            ['Orders scanned', count($plan['orders'])],
            ['Orders requiring new ID', $orderChanges],
            ['Shift sessions scanned', count($plan['sessions'])],
            ['Shift sessions requiring new ID', $sessionChanges],
        ]);
        $this->line('Mapping: ' . $mappingPath);
    }
}
