<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncWhatsappLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SYNC_ENTITY_TYPES = [
        'business_settings',
        'whatsapp_logs'
    ];

    public function handle()
    {
        set_time_limit(300);
        Log::info('Business Settings & Whatsapp Logs started (API-based)');

        try {
            $branchId = config('constants.branch_id');

            if (empty($branchId)) {
                Log::warning('SyncWhatsappLogJob halted: branch/restaurant context missing');
                return;
            }

            // Push locally generated message logs before pulling shared settings and logs.
            $this->syncWhatsappMessageLogs((int) $branchId);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing business settings/whatsapp logs', [
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('SyncWhatsappLogJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function syncWhatsappMessageLogs(int $branchId): void
    {
        $table = 'order_whatsapp_msg_log';

        try {
            $hasPushedColumn = DB::connection('oracle')
                ->getSchemaBuilder()
                ->hasColumn($table, 'is_pushed');

            $logsQuery = DB::connection('oracle')
                ->table($table)
                ->where('branch_id', $branchId);

            if ($hasPushedColumn) {
                $logsQuery->where(function ($query) {
                    $query->where('is_pushed', '!=', 'Y')
                        ->orWhereNull('is_pushed');
                });
            }

            $logs = $logsQuery->orderBy('id')->get();
        } catch (\Exception $e) {
            Log::error('Failed to load WhatsApp message logs for sync', [
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if ($logs->isEmpty()) {
            Log::info('No WhatsApp message logs to sync', ['branch_id' => $branchId]);
            return;
        }

        $chunks = $logs->chunk(50);

        foreach ($chunks as $index => $chunk) {
            // Avoid sending the local delivery marker to the live database.
            $logRecords = $chunk->map(function ($log) use ($hasPushedColumn) {
                $record = (array) $log;
                if ($hasPushedColumn) {
                    unset($record['is_pushed']);
                }

                return $record;
            })->values()->all();
            $logIds = $chunk->pluck('id')->all();

            try {
                $response = Http::timeout(config('services.live_server.timeout', 60))
                    ->withToken(config('services.live_server.token'))
                    ->withoutVerifying()
                    ->retry(3, 1000)
                    ->post(config('services.live_server.url') . '/whatsapp-logs/sync-bulk', [
                        'logs' => $logRecords,
                    ]);

                $responseData = $response->json();
                $batchSynced = $response->successful()
                    && is_array($responseData)
                    && ($responseData['success'] ?? false) === true
                    && ($responseData['failed'] ?? 0) == 0
                    && ($responseData['synced'] ?? 0) === count($logRecords);

                if (!$batchSynced) {
                    Log::error('Failed syncing WhatsApp message logs chunk', [
                        'chunk' => $index + 1,
                        'status' => $response->status(),
                        'response' => $response->body(),
                        'logs_count' => count($logIds),
                    ]);
                    continue;
                }

                if ($hasPushedColumn) {
                    DB::connection('oracle')
                        ->table($table)
                        ->whereIn('id', $logIds)
                        ->update(['is_pushed' => 'Y']);
                }

                Log::info('WhatsApp message logs chunk synced successfully', [
                    'chunk' => $index + 1,
                    'logs_count' => count($logIds),
                    'log_ids' => $logIds,
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error('Connection error syncing WhatsApp message logs chunk', [
                    'chunk' => $index + 1,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                Log::error('Unexpected error syncing WhatsApp message logs chunk', [
                    'chunk' => $index + 1,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    private function pickLatestTimestamp(?string $current, ?string $candidate): ?string
    {
        if (empty($candidate)) {
            return $current;
        }

        try {
            $candidateCarbon = Carbon::parse($candidate);
        } catch (\Exception $e) {
            Log::warning('Invalid candidate timestamp received during business setting sync', [
                'value' => $candidate,
                'error' => $e->getMessage(),
            ]);

            return $current;
        }

        if (empty($current)) {
            return $candidateCarbon->toIso8601String();
        }

        try {
            $currentCarbon = Carbon::parse($current);
        } catch (\Exception $e) {
            Log::warning('Invalid stored timestamp during business setting sync, overriding', [
                'value' => $current,
                'error' => $e->getMessage(),
            ]);

            return $candidateCarbon->toIso8601String();
        }

        return $candidateCarbon->greaterThan($currentCarbon)
            ? $candidateCarbon->toIso8601String()
            : $currentCarbon->toIso8601String();
    }

    private function sendSyncStateUpdate(int $branchId, array $latestSyncedTimestamps): void
    {
        $payload = [
            'branch_id' => $branchId,
        ];

        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            if (empty($latestSyncedTimestamps[$entity])) {
                continue;
            }

            try {
                $payload[$entity . '_last_synced_at'] = Carbon::parse($latestSyncedTimestamps[$entity])->toIso8601String();
            } catch (\Exception $e) {
                Log::warning('Invalid timestamp while preparing business setting sync update', [
                    'entity' => $entity,
                    'timestamp' => $latestSyncedTimestamps[$entity],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (count($payload) === 1) {
            return;
        }

        try {
            $response = Http::timeout(30)
                ->withToken(config('services.live_server.token'))
                ->withoutVerifying()
                ->post(config('services.live_server.url') . '/business-settings-and-whatsapp-logs/update-sync-state', $payload);

            if ($response->successful()) {
                Log::info('Branch sync state updated on live server (business settings/whatsapp logs)', [
                    'branch_id' => $branchId,
                    'entities' => array_keys(array_diff_key($payload, ['branch_id' => null])),
                ]);
            } else {
                Log::warning('Failed to update branch sync state on live server (business settings/whatsapp logs)', [
                    'branch_id' => $branchId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while updating branch sync state (business settings/whatsapp logs)', [
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
