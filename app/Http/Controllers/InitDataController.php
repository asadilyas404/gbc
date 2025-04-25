<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Traits\ActivationClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;

class InitDataController extends Controller
{
    public function initData()
    {
        $this->businessSetting();

    }

    public function businessSetting()
    {
        $data = [
            ['key' => 'cash_on_delivery', 'value' => '{"status":"1"}', 'created_at' => '2021-05-10 22:56:38', 'updated_at' => '2021-09-09 17:27:34'],
            ['key' => 'stripe', 'value' => '{"status":"0","api_key":null,"published_key":null}', 'created_at' => '2021-05-10 22:57:02', 'updated_at' => '2021-09-09 17:28:18'],
            ['key' => 'mail_config', 'value' => '{"status":"1","name":"Food Magic","host":"deploylogics.com","driver":"SMTP","port":"465","username":"foodmagic@deploylogics.com","email_id":"foodmagic@deploylogics.com","encryption":"SSL","password":"Deploy@1122"}', 'created_at' => NULL, 'updated_at' => '2025-01-20 09:31:35'],
            ['key' => 'fcm_project_id', 'value' => NULL],
            ['key' => 'push_notification_key', 'value' => NULL],
            ['key' => 'order_pending_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'order_confirmation_msg', 'value' => '{"status":0,"message":null}'],
            ['key' => 'order_processing_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'out_for_delivery_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'order_delivered_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'delivery_boy_assign_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'delivery_boy_start_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'delivery_boy_delivered_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'terms_and_conditions', 'value' => NULL, 'updated_at' => '2021-06-29 01:44:49'],
            ['key' => 'business_name', 'value' => 'Sufrt Al Malek', 'updated_at' => '2025-02-13 03:26:30'],
            ['key' => 'currency', 'value' => 'OMR', 'updated_at' => '2025-01-01 10:45:38'],
            ['key' => 'logo', 'value' => '2025-02-13-67adba6cc51b5.png', 'updated_at' => '2025-02-13 03:25:00'],
            ['key' => 'phone', 'value' => '78455616', 'updated_at' => '2024-12-15 23:01:02'],
            ['key' => 'email_address', 'value' => 'royal.ghazanfar@gmail.com', 'updated_at' => '2024-12-15 23:01:02'],
            ['key' => 'address', 'value' => 'North Al-Hail, Muscat', 'updated_at' => '2024-12-15 23:01:03'],
            ['key' => 'footer_text', 'value' => 'Footer Text'],
            ['key' => 'customer_verification', 'value' => NULL],
            ['key' => 'map_api_key', 'value' => 'AIzaSyD7-_WkA57hooHEtUOod70TukSC1iuirIY', 'updated_at' => '2024-12-17 09:03:45'],
            ['key' => 'privacy_policy', 'value' => NULL, 'updated_at' => '2021-06-28 04:46:55'],
            ['key' => 'about_us', 'value' => NULL, 'updated_at' => '2021-06-29 01:43:25'],
            ['key' => 'minimum_shipping_charge', 'value' => '0'],
            ['key' => 'per_km_shipping_charge', 'value' => '0'],
            ['key' => 'ssl_commerz_payment', 'value' => '{"status":"0","store_id":null,"store_password":null}'],
            ['key' => 'razor_pay', 'value' => '{"status":"0","razor_key":null,"razor_secret":null}'],
            ['key' => 'digital_payment', 'value' => '{"status":"1"}'],
            ['key' => 'paypal', 'value' => '{"status":"0","paypal_client_id":null,"paypal_secret":null}'],
            ['key' => 'paystack', 'value' => '{"status":"0","publicKey":null,"secretKey":null,"paymentUrl":null,"merchantEmail":null}'],
            ['key' => 'senang_pay', 'value' => '{"status":null,"secret_key":null,"published_key":null,"merchant_id":null}'],
            ['key' => 'order_handover_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'order_cancled_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'timezone', 'value' => 'Asia/Muscat'],
            ['key' => 'order_delivery_verification', 'value' => null],
            ['key' => 'currency_symbol_position', 'value' => 'right'],
            ['key' => 'schedule_order', 'value' => null],
            ['key' => 'app_minimum_version', 'value' => '0'],
            ['key' => 'tax', 'value' => null],
            ['key' => 'admin_commission', 'value' => '5'],
            ['key' => 'country', 'value' => 'AE'],
            ['key' => 'app_url', 'value' => 'up_comming'],
            ['key' => 'default_location', 'value' => '{"lat":"23.6487474","lng":"58.2263177"}'],
            ['key' => 'twilio_sms', 'value' => '{"status":"0","sid":null,"messaging_service_id":null,"token":null,"from":null,"otp_template":"Your otp is #OTP#."}'],
            ['key' => 'nexmo_sms', 'value' => '{"status":"0","api_key":null,"api_secret":null,"from":null,"otp_template":"Your otp is #OTP#."}'],
            ['key' => 'msg91_sms', 'value' => '{"status":"0","template_id":null,"authkey":null}'],
            ['key' => 'admin_order_notification', 'value' => '1'],
            ['key' => 'free_delivery_over', 'value' => null],
            ['key' => 'maintenance_mode', 'value' => '0'],
            ['key' => 'dm_maximum_orders', 'value' => '5'],
            ['key' => 'flutterwave', 'value' => '{"status":"1","public_key":null,"secret_key":null,"hash":null}'],
            ['key' => 'order_confirmation_model', 'value' => 'restaurant'],
            ['key' => 'popular_food', 'value' => '1'],
            ['key' => 'popular_restaurant', 'value' => '1'],
            ['key' => 'new_restaurant', 'value' => '1'],
            ['key' => 'most_reviewed_foods', 'value' => '1'],
            ['key' => 'paymob_accept', 'value' => '{"status":"0","api_key":null,"iframe_id":null,"integration_id":null,"hmac":null}'],
            ['key' => 'timeformat', 'value' => '24'],
            ['key' => 'canceled_by_restaurant', 'value' => '1'],
            ['key' => 'canceled_by_deliveryman', 'value' => '0'],
            ['key' => 'toggle_veg_non_veg', 'value' => '1'],
            ['key' => 'toggle_dm_registration', 'value' => null],
            ['key' => 'toggle_restaurant_registration', 'value' => null],
            ['key' => 'recaptcha', 'value' => '{"status":"0","site_key":null,"secret_key":null}'],
            ['key' => 'language', 'value' => '["en","ar"]', 'updated_at' => '2024-12-19 04:00:27'],
            ['key' => 'schedule_order_slot_duration', 'value' => '0'],
            ['key' => 'digit_after_decimal_point', 'value' => '3', 'updated_at' => '2024-12-15 23:01:03'],
            ['key' => 'icon', 'value' => '2025-02-13-67adbac6cb914.png', 'updated_at' => '2025-02-13 03:26:30'],
            ['key' => 'delivery_charge_comission', 'value' => '0', 'created_at' => '2022-07-03 12:07:00', 'updated_at' => '2022-07-03 12:07:00'],
            ['key' => 'dm_max_cash_in_hand', 'value' => '10000', 'created_at' => '2022-07-03 12:07:00', 'updated_at' => '2022-07-03 12:07:00'],
            ['key' => 'theme', 'value' => '1', 'created_at' => '2022-07-03 12:37:00', 'updated_at' => '2022-07-03 12:37:00'],
            ['key' => 'dm_tips_status', 'value' => NULL],
            ['key' => 'wallet_status', 'value' => '0'],
            ['key' => 'loyalty_point_status', 'value' => '0'],
            ['key' => 'ref_earning_status', 'value' => '0'],
            ['key' => 'wallet_add_refund', 'value' => '0'],
            ['key' => 'loyalty_point_exchange_rate', 'value' => '0'],
            ['key' => 'ref_earning_exchange_rate', 'value' => '0'],
            ['key' => 'loyalty_point_item_purchase_point', 'value' => '0'],
            ['key' => 'loyalty_point_minimum_point', 'value' => '0'],
            ['key' => 'order_refunded_message', 'value' => '{"status":0,"message":null}'],
            ['key' => 'fcm_credentials', 'value' => '{"apiKey":"AIzaSyAYXT4d2mL7w7qU27ySZLfiIf4xNdA9qVw","authDomain":null,"projectId":null,"storageBucket":null,"messagingSenderId":null,"appId":null,"measurementId":null}', 'updated_at' => '2025-02-13 01:44:01'],
            ['key' => 'feature', 'value' => '[]'],
            ['key' => 'tax_included', 'value' => NULL, 'created_at' => '2023-03-19 23:07:28', 'updated_at' => '2023-03-19 23:07:28'],
            ['key' => 'site_direction', 'value' => 'ltr', 'created_at' => '2023-03-19 23:07:28', 'updated_at' => '2023-03-19 23:07:28'],
            ['key' => 'system_language', 'value' => '[{"id":1,"direction":"ltr","code":"en","status":1,"default":true},{"id":2,"direction":"rtl","code":"ar","status":1,"default":false}]', 'created_at' => '2023-07-09 19:56:39', 'updated_at' => '2024-12-19 04:01:48'],
            ['key' => 'take_away', 'value' => '1', 'created_at' => '2023-07-09 19:56:39'],
            ['key' => 'repeat_order_option', 'value' => '1', 'created_at' => '2023-07-09 19:56:39'],
            ['key' => 'home_delivery', 'value' => '1', 'created_at' => '2023-07-09 19:56:39'],
            ['key' => 'refund_active_status', 'value' => '1', 'created_at' => '2023-07-09 19:56:58'],
            ['key' => 'business_model', 'value' => '{"commission":1,"subscription":1}', 'created_at' => '2023-07-09 19:56:58', 'updated_at' => '2024-12-19 03:59:37'],
            ['key' => 'cookies_text', 'value' => 'Cookies'],
            ['key' => 'offline_payment_status', 'value' => '1', 'created_at' => '2025-01-01 10:35:55', 'updated_at' => '2025-01-01 10:35:55'],
            ['key' => '3rd_party_storage', 'value' => '0', 'created_at' => '2025-01-20 09:33:48', 'updated_at' => '2025-01-20 09:33:59'],
            ['key' => 'local_storage', 'value' => '1', 'created_at' => '2025-01-20 09:33:48', 'updated_at' => '2025-01-20 09:34:03']
        ];

        DB::table('business_settings')->insert($data);
    }
}
