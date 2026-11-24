<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\CallCenterSetting;

class CallCenter
{
    public static function enabled(): bool
    {
        return (bool) config('callcenter.enabled');
    }

    /**
     * Request-only POS mode for vendor employees with the call_center module.
     * The restaurant owner login is never treated as a call-center agent.
     */
    public static function isAgent(): bool
    {
        if (!self::enabled() || !auth('vendor_employee')->check()) {
            return false;
        }

        return Helpers::employee_module_permission_check('call_center');
    }

    public static function preparationMinutes(): int
    {
        return self::minutes('preparation_minutes', (int) config('callcenter.preparation_minutes', 20));
    }

    public static function handoverMinutes(): int
    {
        return self::minutes('handover_minutes', (int) config('callcenter.handover_minutes', 5));
    }

    /**
     * Time told to the customer, and the drive back to the branch.
     * The return trip is not included in the customer estimate.
     */
    public static function quote(int $zoneDeliveryMinutes): array
    {
        $preparation = self::preparationMinutes();
        $handover = self::handoverMinutes();
        $zoneDeliveryMinutes = max(0, $zoneDeliveryMinutes);

        return [
            'preparation_minutes' => $preparation,
            'zone_delivery_minutes' => $zoneDeliveryMinutes,
            'handover_minutes' => $handover,
            'customer_eta_minutes' => $preparation + $zoneDeliveryMinutes + $handover,
            'driver_return_minutes' => $zoneDeliveryMinutes,
        ];
    }

    private static function minutes(string $key, int $default): int
    {
        $stored = CallCenterSetting::query()->where('setting_key', $key)->value('setting_value');
        if ($stored === null || $stored === '') {
            return $default;
        }

        return max(0, (int) $stored);
    }
}
