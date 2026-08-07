<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPosOrderVoucherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 90;

    public $orderId;

    public string $action;

    public function __construct($orderId, string $action = 'sync')
    {
        $this->orderId = $orderId;
        $this->action = $action === 'delete' ? 'delete' : 'sync';
    }

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.erp.base_url'), '/');

        if ($baseUrl === '') {
            Log::warning('ERP voucher sync skipped: ERP_BASE_URL not configured', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

        $payload = ['order_id' => $this->orderId];
        if ($this->action === 'delete') {
            $payload['action'] = 'delete';
        }

        $url = $baseUrl . '/api/pos-order-voucher';
        $delays = [0, 2, 3];
        $lastError = null;

        foreach ($delays as $index => $delaySeconds) {
            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }

            try {
                $response = Http::timeout((int) config('services.erp.timeout', 30))
                    ->withoutVerifying()
                    ->acceptJson()
                    ->asJson()
                    ->post($url, $payload);

                $body = $response->body();
                $json = $response->json();

                Log::info('ERP pos-order-voucher response', [
                    'order_id' => $this->orderId,
                    'action' => $this->action,
                    'attempt' => $index + 1,
                    'status' => $response->status(),
                    'request' => $payload,
                    'response' => $json ?? $body,
                ]);

                if ($response->status() === 422) {
                    Log::error('ERP pos-order-voucher validation failed', [
                        'order_id' => $this->orderId,
                        'response' => $json ?? $body,
                    ]);
                    return;
                }

                if ($this->shouldRetry($response->status(), $body, $json)) {
                    $lastError = 'retryable response on attempt ' . ($index + 1);
                    continue;
                }

                if ($response->successful()) {
                    return;
                }

                $lastError = 'HTTP ' . $response->status();
            } catch (ConnectionException $e) {
                $lastError = $e->getMessage();
                Log::error('ERP pos-order-voucher connection error', [
                    'order_id' => $this->orderId,
                    'attempt' => $index + 1,
                    'error' => $lastError,
                ]);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::error('ERP pos-order-voucher unexpected error', [
                    'order_id' => $this->orderId,
                    'attempt' => $index + 1,
                    'error' => $lastError,
                ]);
            }
        }

        Log::error('ERP pos-order-voucher failed after retries', [
            'order_id' => $this->orderId,
            'action' => $this->action,
            'error' => $lastError,
        ]);
    }

    private function shouldRetry(int $status, string $body, $json): bool
    {
        $haystack = strtolower($body . ' ' . json_encode($json));

        if (str_contains($haystack, 'not found') || str_contains($haystack, 'order was not found')) {
            return true;
        }

        if (is_array($json) && isset($json['data']['results']) && is_array($json['data']['results'])) {
            foreach ($json['data']['results'] as $result) {
                if (($result['order_id'] ?? null) == $this->orderId
                    && ($result['action'] ?? '') === 'skipped'
                    && str_contains(strtolower(json_encode($result)), 'not found')
                ) {
                    return true;
                }
            }
        }

        return $status >= 500;
    }
}
