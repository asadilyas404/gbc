<?php

namespace App\Jobs;

use App\Models\OrderWhatsappMsgLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
                Log::warning('SyncBusinessSettingsJob halted: branch/restaurant context missing');
                return;
            }

            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withoutVerifying() // disables SSL certificate verification
                ->withToken(config('services.live_server.token'))
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/business-settings-and-whatsapp-logs/get-data', [
                    'branch_id' => $branchId,
                ]);

            if (!$response->successful()) {
                Log::error('Failed to get business settings and whatsapp logs from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            Log::info('Received business settings and whatsapp logs from live server', [
                'business_settings' => count($data['business_settings'] ?? []),
                'whatsapp_logs' => count($data['whatsapp_logs'] ?? []),
            ]);

            $syncedBusinessSettingIds = [];
            $syncedWhatsappLogIds = [];
            $latestSyncedTimestamps = array_fill_keys(self::SYNC_ENTITY_TYPES, null);

            foreach ($data['business_settings'] ?? [] as $setting) {
                try {
                    DB::connection('oracle')
                        ->table('business_settings')
                        ->updateOrInsert(
                            ['id' => $setting['id']],
                            $setting
                        );

                    $syncedBusinessSettingIds[] = $setting['id'];
                    $latestSyncedTimestamps['business_settings'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['business_settings'],
                        $setting['updated_at'] ?? null
                    );

                } catch (\Exception $e) {
                    Log::error("Failed syncing business setting ID {$setting['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            

            $this->sendSyncStateUpdate((int) $branchId, $latestSyncedTimestamps);

            Log::info('SyncBusinessSettingsJob completed successfully', [
                'synced_business_settings' => count($syncedBusinessSettingIds),
                'synced_whatsapp_logs' => count($syncedWhatsappLogIds)
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing business settings/whatsapp logs', [
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('SyncBusinessSettingsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function copyImageFromStorage($filename, $folder = 'images/')
    {
        $imageSourceBase = config('constants.image_source_base');
        $imageSourceBase = rtrim($imageSourceBase, '/') . '/';
        $relativePath = $folder . $filename;

        $sourceUrl = $imageSourceBase . $relativePath;
        $destinationPath = public_path($relativePath);

        try {
            if (!file_exists(dirname($destinationPath))) {
                mkdir(dirname($destinationPath), 0755, true);
            }

            $imageData = @file_get_contents($sourceUrl);
            if ($imageData === false) {
                Log::warning("Image not found or failed to fetch: {$sourceUrl}");
                return;
            }

            file_put_contents($destinationPath, $imageData);
            Log::info("Image copied to: " . $destinationPath);

        } catch (\Exception $e) {
            Log::error("Image copy failed for {$relativePath}: " . $e->getMessage());
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
