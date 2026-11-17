<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosDiscountService
{
    public const ENABLED_SETTING = 'pos_extra_discount_enabled';

    public function isEnabled(): bool
    {
        $value = DB::table('business_settings')->where('key', self::ENABLED_SETTING)->value('value');
        // Preserve current behavior until an administrator explicitly configures the setting.
        return $value === null || (string) $value === '1';
    }

    public function productDiscounts()
    {
        return DB::table('tbl_pos_discount_types')->where('status', 1)
            ->where(function ($query) {
                $query->where('is_order_discount', 0)->orWhereNull('is_order_discount');
            })->orderBy('name')->get();
    }

    public function orderDiscounts()
    {
        return DB::table('tbl_pos_discount_types')->where('status', 1)
            ->where('is_order_discount', 1)->orderBy('name')->get();
    }

    public function validateProductSelection($id): void
    {
        if ($id === null || $id === '') {
            return;
        }
        $discount = DB::table('tbl_pos_discount_types')->where('id', $id)->where('status', 1)
            ->where(function ($query) {
                $query->where('is_order_discount', 0)->orWhereNull('is_order_discount');
            })->first();
        if (!$discount) {
            $this->fail('Select an active per-product discount.');
        }
    }

    public function hasProductDiscount($cart): bool
    {
        foreach ($cart as $item) {
            if ($this->activeItem($item) && (float) ($item['discount'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }

    public function hasManualProductDiscount($cart): bool
    {
        foreach ($cart as $item) {
            if ($this->activeItem($item) && (float) ($item['discount'] ?? 0) > 0
                && ($item['discount_source'] ?? null) !== 'food_default') {
                return true;
            }
        }
        return false;
    }

    public function hasDefaultFoodDiscount($cart): bool
    {
        foreach ($cart as $item) {
            if ($this->activeItem($item) && ($item['discount_source'] ?? null) === 'food_default'
                && (float) ($item['discount'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }

    public function withoutDefaultFoodDiscount(array $item): array
    {
        if (($item['discount_source'] ?? null) === 'food_default') {
            $item['discount'] = 0;
            $item['discountAmount'] = 0;
            $item['discountType'] = 'percent';
            $item['pos_discount_type'] = null;
            $item['discount_source'] = 'none';
        }
        return $item;
    }

    public function withoutDefaultFoodDiscounts($cart)
    {
        return collect($cart)->map(function ($item) {
            return $this->activeItem($item) ? $this->withoutDefaultFoodDiscount($item) : $item;
        });
    }

    public function isDefaultFoodDiscount($food, $selectedId, float $price, float $amount, bool $legacy = false): bool
    {
        if ($selectedId !== null && $selectedId !== '' && !($legacy && (string) $selectedId === '1')) {
            return false;
        }
        $value = (float) ($food['discount'] ?? 0);
        $expected = ($food['discount_type'] ?? null) === 'percent' ? $price * $value / 100 : $value;
        return $value > 0 && abs(($legacy ? round($expected, 3) : $expected)
            - ($legacy ? round($amount, 3) : $amount)) < 0.000001;
    }

    public function defaultDiscountTypeId()
    {
        // Preserve the legacy default category used by ERP discount reports when it is available.
        return DB::table('tbl_pos_discount_types')->where('id', 1)->where('status', 1)
            ->where(function ($query) {
                $query->where('is_order_discount', 0)->orWhereNull('is_order_discount');
            })->value('id');
    }

    /** Restore the source from the order's food snapshot, including legacy defaults. */
    public function restoreProductDiscount(array $food, $selectedId, float $price, float $amount): array
    {
        $source = $food['pos_discount_source'] ?? null;
        if (!in_array($source, ['food_default', 'manual', 'none'], true)) {
            $source = $this->isDefaultFoodDiscount($food, $selectedId, $price, $amount, true)
                ? 'food_default' : ($amount > 0 ? 'manual' : 'none');
        }
        $value = $amount;
        $type = 'amount';
        if ($source === 'food_default') {
            $value = $food['pos_discount_value'] ?? $food['discount'] ?? $amount;
            $type = $food['pos_discount_value_type'] ?? $food['discount_type'] ?? 'amount';
        }
        return ['discount_source' => $source, 'pos_discount_type' => $selectedId,
            'discountAmount' => $value, 'discountType' => $type];
    }

    public function hasOrderDiscount($cart): bool
    {
        return !empty($cart['order_discount_type_id']) || (float) ($cart['discount'] ?? 0) > 0;
    }

    public function subtotal($cart): float
    {
        $subtotal = 0;
        foreach ($cart as $item) {
            if ($this->activeItem($item)) {
                $subtotal += (float) $item['price'] * (float) $item['quantity']
                    + (float) ($item['addon_price'] ?? 0);
            }
        }
        return $subtotal;
    }

    public function eligibleSubtotal($cart): float
    {
        $subtotal = 0;
        foreach ($cart as $item) {
            if ($this->activeItem($item) && ($item['discount_source'] ?? null) !== 'food_default') {
                $subtotal += (float) $item['price'] * (float) $item['quantity']
                    + (float) ($item['addon_price'] ?? 0);
            }
        }
        return $subtotal;
    }

    /** Always resolve the rate and cap from the ERP catalogue, never form inputs. */
    public function orderDiscount($id)
    {
        $discount = DB::table('tbl_pos_discount_types')->where('id', $id)
            ->where('status', 1)->where('is_order_discount', 1)->first();
        if (!$discount) {
            $this->fail('Select an active overall-bill discount.');
        }
        if (!is_numeric($discount->default_discount) || $discount->default_discount < 0
            || $discount->default_discount > 100 || !is_numeric($discount->max_discount)
            || $discount->max_discount < 0) {
            $this->fail('The selected overall-bill discount has an invalid percentage or maximum amount. Update it in ERP.');
        }
        return $discount;
    }

    public function amount($cart, ?float $subtotal = null): float
    {
        if (!$this->hasOrderDiscount($cart)) {
            return 0;
        }
        if (!$this->isEnabled()) {
            $this->fail('Extra Discount is disabled in Business Setup. Remove the existing extra discount before saving this order.');
        }
        if ($this->hasManualProductDiscount($cart)) {
            $this->fail('Extra discount cannot be combined with manually selected product discounts. Remove the manual product discounts or the extra discount first.');
        }
        $subtotal = max(0, $subtotal ?? $this->eligibleSubtotal($cart));
        if (!empty($cart['order_discount_type_id'])) {
            $discount = $this->orderDiscount($cart['order_discount_type_id']);
            return round(min($subtotal * (float) $discount->default_discount / 100,
                (float) $discount->max_discount), 3);
        }

        // Keep pre-existing draft discounts (which had no catalogue ID) editable.
        $value = $cart['discount'] ?? 0;
        $type = $cart['discount_type'] ?? 'amount';
        if (!is_numeric($value) || $value < 0 || !in_array($type, ['amount', 'percent'], true)
            || ($type === 'percent' && $value > 100)) {
            $this->fail('Remove the invalid extra discount and select an overall-bill discount.');
        }
        $amount = $type === 'percent' ? $subtotal * $value / 100 : (float) $value;
        if (round($amount, 3) > round($subtotal, 3)) {
            $this->fail('Extra discount cannot exceed the order subtotal.');
        }
        return round($amount, 3);
    }

    private function activeItem($item): bool
    {
        return is_array($item) && isset($item['id'], $item['quantity'])
            && trim($item['is_deleted'] ?? 'N') !== 'Y' && (float) $item['quantity'] > 0;
    }

    private function fail(string $message): void
    {
        throw ValidationException::withMessages(['discount' => $message]);
    }
}
