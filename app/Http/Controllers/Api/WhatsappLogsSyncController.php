<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsappLogsSyncController extends Controller
{
    private const SYNC_ENTITY_TYPES = [
        'business_settings',
        'whatsapp_logs',
    ];

    /**
     * Return records changed since the branch's saved sync cursors.
     */
    public function sendData(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|integer',
            ]);

            $branchId = (int) $validated['branch_id'];
            $cursorMap = $this->fetchBranchSyncState($branchId);

            $data = [
                'business_settings' => [],
                'whatsapp_logs' => [],
            ];

            $businessSettings = DB::connection('oracle')
                ->table('business_settings')
                ->when($cursorMap['business_settings'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            $whatsappLogs = DB::connection('oracle')
                ->table('whatsapp_logs')
                ->when($cursorMap['whatsapp_logs'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            $data['business_settings'] = $businessSettings
                ->map(function ($setting) {
                    return (array) $setting;
                })
                ->all();

            foreach ($whatsappLogs as $log) {
                $logRecord = $this->ensureUpdatedAt((array) $log, 'whatsapp_logs', 'id');
                $log->updated_at = $logRecord['updated_at'] ?? $log->updated_at;
                $data['whatsapp_logs'][] = $logRecord;
            }

            Log::info('Business settings and whatsapp logs data retrieved for sync', [
                'branch_id' => $branchId,
                'business_settings_count' => count($data['business_settings']),
                'whatsapp_logs_count' => count($data['whatsapp_logs'])
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'counts' => [
                    'business_settings' => count($data['business_settings']),
                    'whatsapp_logs' => count($data['whatsapp_logs'])
                ],
                'cursor' => array_map(function ($item) {
                    return $item ? $item->toIso8601String() : null;
                }, $this->collectLatestTimestamps($data)),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get business settings and WhatsApp logs for sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve business settings and WhatsApp logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Idempotently upsert a batch of local order WhatsApp message logs.
     */
    public function syncBulk(Request $request)
    {
        $validated = $request->validate([
            'logs' => 'required|array',
            'logs.*' => 'required|array',
            'logs.*.id' => 'required',
        ]);

        $syncedIds = [];
        $failedCount = 0;

        foreach ($validated['logs'] as $log) {
            try {
                DB::connection('oracle')
                    ->table('order_whatsapp_msg_log')
                    ->updateOrInsert(
                        ['id' => $log['id']],
                        $log
                    );

                $syncedIds[] = $log['id'];
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Failed syncing WhatsApp message log', [
                    'log_id' => $log['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => $failedCount === 0,
            'message' => $failedCount === 0
                ? 'WhatsApp message logs synced successfully'
                : 'Some WhatsApp message logs failed to sync',
            'total' => count($validated['logs']),
            'synced' => count($syncedIds),
            'failed' => $failedCount,
            'log_ids' => $syncedIds,
        ], $failedCount === 0 ? 200 : 207);
    }

    /**
     * Save the latest pull cursors for the given branch.
     */
    public function updateSyncState(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|integer',
                'business_settings_last_synced_at' => 'nullable|string',
                'whatsapp_logs_last_synced_at' => 'nullable|string',
            ]);

            $branchId = (int) $validated['branch_id'];
            $timestamps = $this->parseTimestampPayload($validated);
            $this->persistBranchSyncState($branchId, $timestamps);

            Log::info('Branch sync state updated (business settings/WhatsApp logs)', [
                'branch_id' => $branchId,
                'payload' => array_intersect_key(
                    $validated,
                    array_flip(array_map(function ($entity) {
                        return $entity . '_last_synced_at';
                    }, self::SYNC_ENTITY_TYPES))
                ),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch sync state updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update branch sync state (business settings/WhatsApp logs)', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch sync state',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function fetchBranchSyncState(int $branchId): array
    {
        $rows = DB::connection('oracle')
            ->table('branch_sync_state')
            ->where('restaurant_id', $branchId)
            ->whereIn('entity_type', self::SYNC_ENTITY_TYPES)
            ->get()
            ->keyBy('entity_type');

        $map = [];
        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            $timestamp = optional($rows->get($entity))->last_synced_at;
            $map[$entity] = $timestamp ? $this->parseTimestamp($timestamp) : null;
        }

        return $map;
    }

    private function collectLatestTimestamps(array $data): array
    {
        return [
            'business_settings' => $this->extractMaxTimestamp($data['business_settings']),
            'whatsapp_logs' => $this->extractMaxTimestamp($data['whatsapp_logs']),
        ];
    }

    private function extractMaxTimestamp(array $items): ?Carbon
    {
        $max = null;

        foreach ($items as $item) {
            $value = $item['updated_at'] ?? null;
            $timestamp = $this->parseTimestamp($value);

            if (!$timestamp) {
                continue;
            }

            if (!$max || $timestamp->greaterThan($max)) {
                $max = $timestamp;
            }
        }

        return $max;
    }

    private function parseTimestamp(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            Log::warning('Invalid timestamp received during WhatsApp log sync', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parseTimestampPayload(array $validated): array
    {
        $result = [];
        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            $field = $entity . '_last_synced_at';
            $timestamp = $this->parseTimestamp($validated[$field] ?? null);
            if ($timestamp) {
                $result[$entity] = $timestamp;
            }
        }

        return $result;
    }

    private function persistBranchSyncState(int $branchId, array $timestamps): void
    {
        if (empty($timestamps)) {
            return;
        }

        foreach ($timestamps as $entity => $timestamp) {
            $query = DB::connection('oracle')
                ->table('branch_sync_state')
                ->where('restaurant_id', $branchId)
                ->where('entity_type', $entity);

            $values = [
                'last_synced_at' => $timestamp->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            if ($query->exists()) {
                $query->update($values);
                continue;
            }

            DB::connection('oracle')
                ->table('branch_sync_state')
                ->insert(array_merge($values, [
                    'restaurant_id' => $branchId,
                    'entity_type' => $entity,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]));
        }
    }

    private function ensureUpdatedAt(array $record, string $table, string $primaryKey): array
    {
        if (!array_key_exists($primaryKey, $record)) {
            Log::warning('Missing primary key while ensuring updated_at', [
                'table' => $table,
                'primary_key' => $primaryKey,
            ]);

            return $record;
        }

        if (!empty($record['updated_at'])) {
            return $record;
        }

        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        DB::connection('oracle')
            ->table($table)
            ->where($primaryKey, $record[$primaryKey])
            ->update([
                'updated_at' => $timestamp,
            ]);

        $record['updated_at'] = $timestamp;

        return $record;
    }
}
