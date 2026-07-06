<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\Food;
use App\Models\OptionsList;
use App\Models\Order;
use App\Models\VariationOption;
use ArPHP\I18N\Arabic;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class WhatsappService
{
    protected $phoneNoId;
    protected $token;
    protected $apiVersion;

    public function __construct()
    {
        $this->phoneNoId = config('whatsapp.whatsapp_phone_number_id');
        $this->token = config('whatsapp.whatsapp_token');
        $this->apiVersion = config('whatsapp.whatsapp_api_version', 'v20.0');
    }

    public function sendOrderConfirmationMessage($to, $order, $status = 'new')
    {
        try {
            $items = $order->details ?? [];
            $itemCount = count($items);

            if ($itemCount < 1) {
                return [
                    'success' => false,
                    'message' => 'Order must have at least 1 item.',
                ];
            }

            $templateName = "malek_al_pizza_items_1_order_confirmation";

            $parameters = [];

            /*
            |--------------------------------------------------------------------------
            | Common parameters
            |--------------------------------------------------------------------------
            | Keep this order exactly same as your WhatsApp template variables.
            |--------------------------------------------------------------------------
            */

            $pdf = $this->savePDFOnServer($order->id);
            dd($pdf);
            if (!$pdf) {
                return [
                    'success' => false,
                    'message' => 'PDF upload failed.',
                ];
            }

            $headerParameters = [
                [
                    'type' => 'document',
                    'document' => [
                        'link' => $pdf['url'],
                        'filename' => $pdf['file_name'],
                    ],
                ],
            ];

            $order->restaurant->load('branch');
            $parameters[] = $this->textParam('order_status_ar',$status == 'new' ? 'استلام' : 'تعديل');
            $parameters[] = $this->textParam('branch_name_ar',config('constants.invoice_branch_name') ?? '');
            $parameters[] = $this->textParam('order_status_en',$status == 'new' ? 'received' : 'modified');
            $parameters[] = $this->textParam('branch_name_en',$order->restaurant->branch->branch_name ?? '');
            $parameters[] = $this->textParam('order_id',$order['order_serial'] ?? '');

            /*
            |--------------------------------------------------------------------------
            | Amounts / footer parameters
            |--------------------------------------------------------------------------
            */

            $amounts = $this->padAmounts([
                'discount_amnt' => $totalDiscountPrice ?? '0.000 OMR',
                'add_on_amnt' => $totalAdOnPrice ?? '0.000 OMR',
                'delivery_amnt' => $order['delivery_charge'] ?? '0.000 OMR',
                'total_amnt' => $order['order_amount'] ?? '0.000 OMR',
            ]);

            // $parameters[] = $this->textParam('discount_amnt',$amounts['discount_amnt'] ?? '0.000 OMR');
            // $parameters[] = $this->textParam('add_on_amnt',$amounts['add_on_amnt'] ?? '0.000 OMR');
            // $parameters[] = $this->textParam('delivery_amnt',$amounts['delivery_amnt'] ?? '0.000 OMR');
            $parameters[] = $this->textParam('total_amnt',$amounts['total_amnt'] ?? '0.000 OMR');
            $parameters[] = $this->textParam('branch_no_ar',$order->restaurant->phone ?? '-');
            $parameters[] = $this->textParam('branch_no_en',$order->restaurant->phone ?? '-');

            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNoId}/messages";

            $components = [];

            // Add header only if header parameters exist
            if (!empty($headerParameters)) {
                $components[] = [
                    'type' => 'header',
                    'parameters' => $headerParameters,
                ];
            }

            // Add body only if body parameters exist
            if (!empty($parameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $parameters,
                ];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'ar',
                    ],
                    'components' => $components,
                ],
            ];

            $response = Http::withToken($this->token)
                ->acceptJson()
                ->post($url, $payload);

                dd($response->json());

            if ($response->failed()) {
                Log::error('WhatsApp order confirmation failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'template_name' => $templateName,
                    'payload' => $payload,
                ]);

                return [
                    'success' => false,
                    'status' => $response->status(),
                    'template_name' => $templateName,
                    'response' => $response->json(),
                ];
            }

            return [
                'success' => true,
                'template_name' => $templateName,
                'response' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp order confirmation exception', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function textParam($name,$text)
    {
        return [
            'type' => 'text',
            'parameter_name' => $name,
            'text' => $this->cleanParam($text),
        ];
    }

    private function cleanParam($text)
    {
        $text = (string) $text;

        // WhatsApp template params cannot have new lines or tabs
        $text = str_replace(["\r\n", "\r", "\n", "\t"], ' | ', $text);

        // WhatsApp does not allow more than 4 consecutive spaces
        $text = preg_replace('/ {5,}/', '    ', $text);

        return trim($text);
    }

    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) == 8) {
            $phone = '968' . $phone;
        }

        return $phone;
    }

    private function padAmounts(array $amounts, string $defaultCurrency = 'OMR')
    {
        $parsed = [];
        $maxLength = 0;

        foreach ($amounts as $key => $amount) {
            $amount = trim((string) $amount);

            // Remove extra spaces
            $amount = preg_replace('/\s+/', ' ', $amount);

            // Match number and optional currency
            preg_match('/^([0-9]+(?:\.[0-9]+)?)(?:\s+(.+))?$/u', $amount, $matches);

            $number = $matches[1] ?? $amount;
            $currency = $matches[2] ?? $defaultCurrency;

            // If currency is empty, use default
            $currency = trim($currency) !== '' ? trim($currency) : $defaultCurrency;

            $maxLength = max($maxLength, strlen($number));

            $parsed[$key] = [
                'number' => number_format($number, 3),
                'currency' => $currency,
            ];
        }

        $result = [];

        foreach ($parsed as $key => $value) {
            $number = str_pad($value['number'], $maxLength, ' ', STR_PAD_RIGHT);

            // Do not trim here, otherwise padding will be removed
            $result[$key] = $number . ' ' . $value['currency'];
        }

        return $result;
    }

    function padItemPrice($amount, $maxNumberLength = 6, $defaultCurrency = 'OMR')
    {
        $amount = trim((string) $amount);

        // Remove extra spaces
        $amount = preg_replace('/\s+/', ' ', $amount);

        // Match number and optional currency
        preg_match('/^([0-9]+(?:\.[0-9]+)?)(?:\s+(.+))?$/u', $amount, $matches);

        $number = $matches[1] ?? '0.000';
        $currency = $matches[2] ?? $defaultCurrency;

        $number = number_format($number, 3);

        $currency = trim($currency) !== '' ? trim($currency) : $defaultCurrency;

        $paddedNumber = str_pad($number, $maxNumberLength, ' ', STR_PAD_RIGHT);

        return $paddedNumber . ' ' . $currency;
    }

    public function savePDFOnServer($orderId)
    {
        if(!$orderId) {
            return false;
        }
        
        // Find the order
        $order = Order::with([
            'restaurant',
            'restaurant.translations',
            'details.food',
            'takenBy',
            'pos_details',
            'partner',
        ])->where('id', $orderId)->firstOrFail();

        // Check if ALL details have is_deleted = 'Y'
        $allDeleted = $order->details->every(function ($detail) {
            return $detail->is_deleted === 'Y';
        });

        if ($allDeleted) {
            return false;
        }

        $branchId = $order->restaurant_id;

        $view = View::make('bill-pdf', ['order' => $order])->render();

        $Arabic = new Arabic();
        $p = $Arabic->arIdentify($view);
        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $Arabic->utf8Glyphs(substr($view, $p[$i-1], $p[$i] - $p[$i-1]));
            $view   = substr_replace($view, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }
        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set('dpi', 100);
        $options->set('isPhpEnabled', TRUE);
        $options->set('isHtml5ParserEnabled', TRUE);
        $options->set('logOutputFile' , storage_path('logs/log.htm'));
        $options->set('tempDir' , storage_path('logs/'));
        $options->setDefaultFont('DejaVuSans');
        $dompdf->setOptions($options);
        $dompdf->loadHtml($view,'UTF-8');
        // (Optional) Setup the paper size and orientation
        $paper_orientation = 'portrait';
        // $customPaper = array(25,0,272,1122);
        $customPaper = array(0,0,242,1122);
        $dompdf->setPaper($customPaper);
        // Render the HTML as PDF
        $dompdf->render();
        $content = $dompdf->output();
        if (!file_exists(public_path('/uploads/attachments/'))) {
            mkdir(public_path('/uploads/attachments/'), 0777, true);
        }

        $fileName = time() . '_' . $order->id . '.pdf';

        $localFolderPath = public_path('uploads/attachments');

        if (!file_exists($localFolderPath)) {
            mkdir($localFolderPath, 0755, true);
        }

        $localPath = $localFolderPath . DIRECTORY_SEPARATOR . $fileName;

        file_put_contents($localPath, $content);

        $serverUrl = config('services.live_server.url') . '/upload-order-pdf';

        $response = Http::attach(
            'pdf',
            file_get_contents($localPath),
            $fileName
        )->post($serverUrl, [
            'secret_key' => config('services.sync_api.token'),
        ]);

        if ($response->failed()) {
            Log::error('PDF upload to live server failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        }

        $data = $response->json();

        return [
            'file_name' => $data['file_name'],
            'url' => $data['url'],
        ];
    }
}