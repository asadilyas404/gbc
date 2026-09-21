<?php

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SslCommerzPaymentController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\PaymobController;
use App\Http\Controllers\FlutterwaveV3Controller;
use App\Http\Controllers\PaytmController;
use App\Http\Controllers\PaypalPaymentController;
use App\Http\Controllers\PaytabsController;
use App\Http\Controllers\LiqPayController;
use App\Http\Controllers\RazorPayController;
use App\Http\Controllers\SenangPayController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\BkashPaymentController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\FirebaseController;
use App\Http\Controllers\InitDataController;
use App\Http\Controllers\TableEmployeeController;
use App\Http\Controllers\VariationController;

Route::post('/variation-delete', [VariationController::class, 'variationDelete'])->name('variation.delete');

Route::resource('table_employees', TableEmployeeController::class);



Route::get('/test-whatsapp-requirements', function (\Illuminate\Http\Request $request) {
    $results = [];

    // 1. Check PHP Extensions & Dompdf
    $results['php_extensions'] = [
        'title' => '1. PHP Extensions & PDF Generator',
        'curl_installed' => extension_loaded('curl'),
        'dompdf_installed' => class_exists('Dompdf\Dompdf'),
        'attachments_dir_writable' => is_writable(public_path('uploads/attachments')) || is_writable(public_path()),
        'status' => (extension_loaded('curl') && class_exists('Dompdf\Dompdf')) ? 'PASS' : 'FAIL',
    ];

    // 2. Check Database Tables & Queue State
    $hasLogTable = \Illuminate\Support\Facades\Schema::hasTable('order_whatsapp_msg_log');
    $hasJobsTable = \Illuminate\Support\Facades\Schema::hasTable('jobs');
    $hasFailedJobsTable = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs');

    $pendingJobsCount = $hasJobsTable ? \Illuminate\Support\Facades\DB::table('jobs')->where('queue', 'whatsapp')->count() : 0;
    $failedJobsCount = $hasFailedJobsTable ? \Illuminate\Support\Facades\DB::table('failed_jobs')->count() : 0;
    $totalLogs = $hasLogTable ? \Illuminate\Support\Facades\DB::table('order_whatsapp_msg_log')->count() : 0;
    $failedLogs = $hasLogTable ? \Illuminate\Support\Facades\DB::table('order_whatsapp_msg_log')->where('message_status', 'failed')->count() : 0;

    $results['database'] = [
        'title' => '2. Database Tables & Queue Worker State',
        'order_whatsapp_msg_log_table' => $hasLogTable ? 'EXISTS' : 'MISSING',
        'jobs_table' => $hasJobsTable ? 'EXISTS' : 'MISSING',
        'failed_jobs_table' => $hasFailedJobsTable ? 'EXISTS' : 'MISSING',
        'pending_whatsapp_jobs_in_db' => $pendingJobsCount,
        'failed_jobs_in_db' => $failedJobsCount,
        'total_whatsapp_logs' => $totalLogs,
        'failed_whatsapp_logs' => $failedLogs,
        'queue_worker_status_hint' => $pendingJobsCount > 0 ? 'WARNING: There are ' . $pendingJobsCount . ' pending WhatsApp jobs in the database! Queue worker may NOT be running.' : 'OK (No stuck jobs)',
        'status' => ($hasLogTable && $hasJobsTable) ? ($pendingJobsCount > 5 ? 'WARNING' : 'PASS') : 'FAIL',
    ];

    // 3. Environment Variables (Meta WhatsApp)
    $mode = config('whatsapp.whatsapp_mode') ?? env('WHATSAPP_MODE');
    $phoneNoId = config('whatsapp.whatsapp_phone_number_id');
    $token = config('whatsapp.whatsapp_token');
    $apiVersion = config('whatsapp.whatsapp_api_version', 'v20.0');

    $metaConfigPass = !empty($mode) && !empty($phoneNoId) && !empty($token);

    $results['meta_whatsapp_config'] = [
        'title' => '3. Meta WhatsApp API Credentials (.env)',
        'mode' => $mode ?? 'NOT SET (Must be LIVE or SANDBOX)',
        'phone_number_id' => !empty($phoneNoId) ? 'CONFIGURED (' . substr($phoneNoId, 0, 4) . '***)' : 'MISSING',
        'token' => !empty($token) ? 'CONFIGURED (' . substr($token, 0, 10) . '***)' : 'MISSING',
        'api_version' => $apiVersion,
        'status' => $metaConfigPass ? 'PASS' : 'FAIL',
    ];

    // 4. Meta WhatsApp API Connectivity Test
    $metaApiStatus = 'NOT TESTED';
    $metaApiDetails = null;
    if ($metaConfigPass) {
        try {
            $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNoId}";
            $res = \Illuminate\Support\Facades\Http::timeout(10)->withToken($token)->get($url);
            if ($res->successful()) {
                $metaApiStatus = 'PASS';
                $metaApiDetails = 'Successfully connected to Meta API. Phone ID verified.';
            } else {
                $metaApiStatus = 'FAIL';
                $metaApiDetails = 'Meta API returned error ' . $res->status() . ': ' . json_encode($res->json());
            }
        } catch (\Throwable $e) {
            $metaApiStatus = 'FAIL';
            $metaApiDetails = 'Connection exception: ' . $e->getMessage();
        }
    }
    $results['meta_whatsapp_api_ping'] = [
        'title' => '4. Meta WhatsApp API Live Ping Test',
        'status' => $metaApiStatus,
        'details' => $metaApiDetails,
    ];

    // 5. Live Server Connectivity (for PDF upload)
    $liveServerUrl = config('services.live_server.url');
    $syncToken = config('services.sync_api.token');
    $liveServerPingStatus = 'NOT TESTED';
    $liveServerPingDetails = null;

    if (!empty($liveServerUrl) && !empty($syncToken)) {
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(10)
                ->withToken($syncToken)
                ->withoutVerifying()
                ->get($liveServerUrl . '/api/health-check');

            if ($res->successful() || in_array($res->status(), [200, 404, 401])) {
                $liveServerPingStatus = 'PASS';
                $liveServerPingDetails = 'Live server is reachable (HTTP ' . $res->status() . ').';
            } else {
                $liveServerPingStatus = 'WARNING';
                $liveServerPingDetails = 'Live server responded with HTTP ' . $res->status();
            }
        } catch (\Throwable $e) {
            $liveServerPingStatus = 'FAIL';
            $liveServerPingDetails = 'Cannot connect to Live Server: ' . $e->getMessage() . '. PDF upload will fail!';
        }
    }

    $results['live_server_sync'] = [
        'title' => '5. Live Server Connectivity (PDF Upload Requirement)',
        'live_server_url' => $liveServerUrl ?? 'MISSING',
        'sync_token' => !empty($syncToken) ? 'CONFIGURED' : 'MISSING',
        'ping_status' => $liveServerPingStatus,
        'ping_details' => $liveServerPingDetails,
        'status' => (!empty($liveServerUrl) && !empty($syncToken) && $liveServerPingStatus !== 'FAIL') ? 'PASS' : 'FAIL',
    ];

    // Overall summary calculation
    $allStatuses = array_column($results, 'status');
    $overallPass = !in_array('FAIL', $allStatuses);

    $summary = [
        'overall_result' => $overallPass ? 'ALL REQUIREMENTS FULFILLED ✅' : 'REQUIREMENTS MISSING / FAILED ❌',
        'timestamp' => now()->toDateTimeString(),
        'branch_id' => config('constants.branch_id') ?? env('BRANCH_ID') ?? 'Default',
    ];

    return response()->json([
        'summary' => $summary,
        'checks' => $results
    ], $overallPass ? 200 : 422, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

Route::get('/test-pusher', function () {
    event(new \App\Events\myevent('Hello from Laravel!'));
    return 'event sent';
});

Route::get('/ws-test', function () {
    $v = time();
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>WebSocket Diagnostic</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script src="/assets/admin/js/pusher.min.js?v={$v}"></script>
</head>
<body style="background:#1e1e1e;color:#fff;font-family:sans-serif;padding:30px;">
<h2>WebSocket Network Diagnostic Test</h2>
<div id="status" style="padding:15px;background:#333;margin-bottom:20px;border-radius:5px;">Testing connection...</div>
<pre id="log" style="background:#111;color:#0f0;padding:20px;font-size:14px;border-radius:5px;min-height:300px;"></pre>

<script>
function log(msg) {
    var logEl = document.getElementById("log");
    if (logEl) {
        logEl.textContent += new Date().toLocaleTimeString() + " | " + msg + String.fromCharCode(10);
    }
    console.log(msg);
}

var wsHost = window.location.hostname;
var wsPort = 6001;
var appKey = "app-key";
var wsUrl = "ws://" + wsHost + ":" + wsPort + "/app/" + appKey + "?protocol=7&client=js&version=8.4.0&flash=false";

log("TEST 1: Connecting raw WebSocket to: " + wsUrl);

try {
    var ws = new WebSocket(wsUrl);

    ws.onopen = function() {
        var statusEl = document.getElementById("status");
        if (statusEl) {
            statusEl.style.background = "#28a745";
            statusEl.innerHTML = "SUCCESS: Raw WebSocket Connected to " + wsHost + ":" + wsPort;
        }
        log("SUCCESS: Raw WebSocket connection established!");
        log("Sending pusher:subscribe for my-channel...");
        ws.send(JSON.stringify({
            event: "pusher:subscribe",
            data: { auth: "", channel: "my-channel" }
        }));
    };

    ws.onmessage = function(event) {
        log("EVENT RECEIVED ON RAW WEBSOCKET: " + event.data);
    };

    ws.onerror = function(err) {
        var statusEl = document.getElementById("status");
        if (statusEl) {
            statusEl.style.background = "#dc3545";
            statusEl.innerHTML = "FAILED: Connection to port 6001 blocked or refused!";
        }
        log("ERROR: WebSocket connection failed! Check Windows Firewall for port 6001.");
    };

    ws.onclose = function(event) {
        log("Raw WebSocket closed. Code=" + event.code);
    };
} catch(e) {
    log("RAW WEBSOCKET EXCEPTION: " + e.message);
}

setTimeout(function() {
    log("");
    log("TEST 2: Testing Pusher JS client library...");
    if (typeof Pusher === "undefined") {
        log("ERROR: Pusher JS library not loaded.");
        return;
    }
    log("Pusher JS library loaded successfully! Version: " + (Pusher.VERSION || "8.4.0"));
    Pusher.logToConsole = true;

    try {
        var pusher = new Pusher("app-key", {
            cluster: "mt1",
            wsHost: window.location.hostname,
            wsPort: 6001,
            forceTLS: false,
            disableStats: true,
            enabledTransports: ["ws"]
        });

        pusher.connection.bind("state_change", function(s) {
            log("PUSHER CLIENT STATE CHANGE: " + s.previous + " => " + s.current);
        });

        pusher.connection.bind("connected", function() {
            log("SUCCESS: Pusher JS Client connected! Socket ID: " + pusher.connection.socket_id);
            var channel = pusher.subscribe("my-channel");
            channel.bind("my-event", function(data) {
                log("MY-EVENT RECEIVED BY PUSHER CLIENT: " + JSON.stringify(data));
            });
            log("Subscribed to my-channel. Now trigger event in another tab: http://" + window.location.hostname + ":8000/test-pusher");
        });
    } catch(e) {
        log("PUSHER CONSTRUCTOR ERROR: " + (e.stack || e.message || e));
    }
}, 1000);
</script>
</body>
</html>
HTML;
    return response($html, 200, [
        'Content-Type' => 'text/html',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0'
    ]);
});
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/init-data', [InitDataController::class, 'initData']);
Route::get('/get-tbl', function(){
    $bb = BusinessSetting::get();
    dump($bb->toArray());
    $cc = \App\Models\Currency::get();
    dump($cc->toArray());
});
Route::post('/subscribeToTopic', [FirebaseController::class, 'subscribeToTopic']);
Route::get('/', 'HomeController@index')->name('home');
Route::view('subscription/payment/view' , 'Subscription_payment_view')->name('subscription_payment_view');
Route::get('maintenance-mode', 'HomeController@maintenanceMode')->name('maintenance_mode');
// ->middleware('maintenance')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

//login

Route::get('login/{tab}', 'LoginController@login')->name('login');
Route::post('login_submit', 'LoginController@submit')->name('login_post')->middleware('actch');
Route::get('logout', 'LoginController@logout')->name('logout');
Route::get('/reload-captcha', 'LoginController@reloadCaptcha')->name('reload-captcha');
Route::get('/reset-password', 'LoginController@reset_password_request')->name('reset-password');
Route::post('/vendor-reset-password', 'LoginController@vendor_reset_password_request')->name('vendor-reset-password');
Route::get('/password-reset', 'LoginController@reset_password')->name('change-password');
Route::post('verify-otp', 'LoginController@verify_token')->name('verify-otp');
Route::post('reset-password-submit', 'LoginController@reset_password_submit')->name('reset-password-submit');
Route::get('otp-resent', 'LoginController@otp_resent')->name('otp_resent');



Route::get('lang/{locale}', 'HomeController@lang')->name('lang');
Route::get('terms-and-conditions', 'HomeController@terms_and_conditions')->name('terms-and-conditions');
Route::get('about-us', 'HomeController@about_us')->name('about-us');
Route::match(['get', 'post'],'contact-us', 'HomeController@contact_us')->name('contact-us');
Route::get('privacy-policy', 'HomeController@privacy_policy')->name('privacy-policy');
Route::post('newsletter/subscribe', 'NewsletterController@newsLetterSubscribe')->name('newsletter.subscribe');

Route::get('refund-policy', 'HomeController@refund_policy')->name('refund-policy');
Route::get('shipping-policy', 'HomeController@shipping_policy')->name('shipping-policy');
Route::get('cancellation-policy', 'HomeController@cancellation_policy')->name('cancellation-policy');



Route::get('subscription-invoice/{id}', 'HomeController@subscription_invoice')->name('subscription_invoice');



Route::get('authentication-failed', function () {
    $errors = [];
    array_push($errors, ['code' => 'auth-001', 'message' => 'Unauthenticated.']);
    return response()->json([
        'errors' => $errors,
    ], 401);
})->name('authentication-failed');

Route::group(['prefix' => 'payment-mobile'], function () {
    Route::get('/', 'PaymentController@payment')->name('payment-mobile');
    Route::get('set-payment-method/{name}', 'PaymentController@set_payment_method')->name('set-payment-method');
});

Route::get('payment-success', 'PaymentController@success')->name('payment-success');
Route::get('payment-fail', 'PaymentController@fail')->name('payment-fail');
Route::get('payment-cancel', 'PaymentController@cancel')->name('payment-cancel');

Route::get('wallet-payment','WalletPaymentController@make_payment')->name('wallet.payment');

$is_published = 0;
try {
$full_data = include('Modules/Gateways/Addon/info.php');
$is_published = $full_data['is_published'] == 1 ? 1 : 0;
} catch (\Exception $exception) {}

if (!$is_published) {
    Route::group(['prefix' => 'payment'], function () {

        //SSLCOMMERZ
        Route::group(['prefix' => 'sslcommerz', 'as' => 'sslcommerz.'], function () {
            Route::get('pay', [SslCommerzPaymentController::class, 'index'])->name('pay');
            Route::post('success', [SslCommerzPaymentController::class, 'success'])
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('failed', [SslCommerzPaymentController::class, 'failed'])
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('canceled', [SslCommerzPaymentController::class, 'canceled'])
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //STRIPE
        Route::group(['prefix' => 'stripe', 'as' => 'stripe.'], function () {
            Route::get('pay', [StripePaymentController::class, 'index'])->name('pay');
            Route::get('token', [StripePaymentController::class, 'payment_process_3d'])->name('token')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::get('success', [StripePaymentController::class, 'success'])->name('success')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //RAZOR-PAY
        Route::group(['prefix' => 'razor-pay', 'as' => 'razor-pay.'], function () {
            Route::get('pay', [RazorPayController::class, 'index']);
            Route::post('payment', [RazorPayController::class, 'payment'])->name('payment')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //PAYPAL
        Route::group(['prefix' => 'paypal', 'as' => 'paypal.'], function () {
            Route::get('pay', [PaypalPaymentController::class, 'payment']);
            Route::any('success', [PaypalPaymentController::class, 'success'])->name('success')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('cancel', [PaypalPaymentController::class, 'cancel'])->name('cancel')
                ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //SENANG-PAY
        Route::group(['prefix' => 'senang-pay', 'as' => 'senang-pay.'], function () {
            Route::get('pay', [SenangPayController::class, 'index']);
            Route::any('callback', [SenangPayController::class, 'return_senang_pay'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //PAYTM
        Route::group(['prefix' => 'paytm', 'as' => 'paytm.'], function () {
            Route::get('pay', [PaytmController::class, 'payment']);
            Route::any('response', [PaytmController::class, 'callback'])->name('response')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //FLUTTERWAVE
        Route::group(['prefix' => 'flutterwave-v3', 'as' => 'flutterwave-v3.'], function () {
            Route::get('pay', [FlutterwaveV3Controller::class, 'initialize'])->name('pay');
            Route::get('callback', [FlutterwaveV3Controller::class, 'callback'])->name('callback')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //PAYSTACK
        Route::group(['prefix' => 'paystack', 'as' => 'paystack.'], function () {
            Route::get('pay', [PaystackController::class, 'index'])->name('pay');
            Route::post('payment', [PaystackController::class, 'redirectToGateway'])->name('payment')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::get('callback', [PaystackController::class, 'handleGatewayCallback'])->name('callback')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //BKASH

        Route::group(['prefix' => 'bkash', 'as' => 'bkash.'], function () {
            // Payment Routes for bKash
            Route::get('make-payment', [BkashPaymentController::class, 'make_tokenize_payment'])->name('make-payment');
            Route::any('callback', [BkashPaymentController::class, 'callback'])->name('callback')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

            // Refund Routes for bKash
            // Route::get('refund', 'BkashRefundController@index')->name('bkash-refund');
            // Route::post('refund', 'BkashRefundController@refund')->name('bkash-refund');
        });

        //Liqpay
        Route::group(['prefix' => 'liqpay', 'as' => 'liqpay.'], function () {
            Route::get('pay', [LiqPayController::class, 'payment'])->name('payment');
            Route::any('callback', [LiqPayController::class, 'callback'])->name('callback')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //MERCADOPAGO
        Route::group(['prefix' => 'mercadopago', 'as' => 'mercadopago.'], function () {
            Route::get('pay', [MercadoPagoController::class, 'index'])->name('index');
            Route::any('make-payment', [MercadoPagoController::class, 'make_payment'])->name('make_payment')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::get('success', [MercadoPagoController::class, 'success'])->name('success');
            Route::get('failed', [MercadoPagoController::class, 'failed'])->name('failed');
        });

        //PAYMOB
        Route::group(['prefix' => 'paymob', 'as' => 'paymob.'], function () {
            Route::any('pay', [PaymobController::class, 'credit'])->name('pay');
            Route::any('callback', [PaymobController::class, 'callback'])->name('callback')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });

        //PAYTABS
        Route::group(['prefix' => 'paytabs', 'as' => 'paytabs.'], function () {
            Route::any('pay', [PaytabsController::class, 'payment'])->name('pay');
            Route::any('callback', [PaytabsController::class, 'callback'])->name('callback')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('response', [PaytabsController::class, 'response'])->name('response')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });
    });
}





Route::get('/404',function (){
    return view('errors.404');
});

Route::get('authentication-failed', function () {
    $errors = [];
    array_push($errors, ['code' => 'auth-001', 'message' => 'Unauthorized.']);
    return response()->json([
        'errors' => $errors
    ], 401);
})->name('authentication-failed');


//Restaurant Registration
Route::group(['prefix' => 'restaurant', 'as' => 'restaurant.'], function () {
    Route::get('apply', 'VendorController@create')->name('create');
    Route::post('apply', 'VendorController@store')->name('store');

    Route::get('back/{restaurant_id}', 'VendorController@back')->name('back');
    Route::post('payment', 'VendorController@payment')->name('payment');
    Route::post('business-plan', 'VendorController@business_plan')->name('business_plan');
    Route::get('final-step', 'VendorController@final_step')->name('final_step');
});

//Deliveryman Registration
Route::group(['prefix' => 'deliveryman', 'as' => 'deliveryman.'], function () {
    Route::get('apply', 'DeliveryManController@create')->name('create');
    Route::post('apply', 'DeliveryManController@store')->name('store');
});


Route::get('/qz/cert', function () {
    $cert = file_get_contents(storage_path(env('QZ_CERT_PATH')));
    return response($cert, 200)->header('Content-Type', 'text/plain');
});

Route::post('/qz/sign', function (\Illuminate\Http\Request $request) {
    $data = $request->input('data');

    $privateKeyPath = storage_path(env('QZ_KEY_PATH'));
    if (!file_exists($privateKeyPath)) {
        return response()->json(['error' => 'Private key not found'], 500);
    }

    $privateKeyContent = file_get_contents($privateKeyPath);
    $privateKey = openssl_pkey_get_private($privateKeyContent);
    if (!$privateKey) {
        return response()->json(['error' => 'Invalid private key'], 500);
    }

    $signature = '';
    if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
        return response()->json(['error' => 'Signing failed'], 500);
    }

    $encoded = base64_encode($signature);
    return response()->json(['signature' => $encoded]);
});

