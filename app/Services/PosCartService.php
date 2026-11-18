<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use Illuminate\Validation\ValidationException;

class PosCartService
{
    public function nonnegative($value, string $field): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
            throw ValidationException::withMessages([$field => 'POS ' . str_replace('_', ' ', $field) . ' must be a valid nonnegative number.']);
        }
        return (float) $value;
    }

    public function validateItem(array $item): void
    {
        $quantity = $this->nonnegative($item['quantity'] ?? null, 'quantity');
        if ($quantity < 1 || floor($quantity) !== $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Food quantity must be a positive whole number.']);
        }
        $price = $this->nonnegative($item['price'] ?? null, 'price');
        $discount = $this->nonnegative($item['discount'] ?? 0, 'product_discount');
        // Both values are per unit. Comparing against price * quantity permits negative sales.
        if ($discount > $price) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the product price.']);
        }
        $this->nonnegative($item['addon_price'] ?? 0, 'addon_price');
        $this->nonnegative($price * $quantity, 'item_total');
        foreach ($item['variations'] ?? [] as $variation) {
            foreach ($variation['addons'] ?? [] as $addon) {
                $this->nonnegative($addon['price'] ?? null, 'addon_price');
            }
        }
    }

    private function addonQuantity($value): int
    {
        $quantity = $this->nonnegative($value, 'addon_quantity');
        if ($quantity < 1 || floor($quantity) !== $quantity) {
            throw ValidationException::withMessages(['addon_quantity' => 'Add-on quantity must be a positive whole number.']);
        }
        return (int) $quantity;
    }

    private function addonMode($mode, $quantity, $foodQuantity): string
    {
        $mode = $mode ?? ((float) $quantity === (float) $foodQuantity ? 'food' : 'manual');
        if (!in_array($mode, ['food', 'manual'], true)) {
            throw ValidationException::withMessages(['addon_quantity' => 'Invalid add-on quantity mode.']);
        }
        return $mode;
    }

    public function withQuantity(array $item, int $quantity): array
    {
        // Resolve legacy quantities against the old food quantity before changing it.
        foreach ($item['add_ons'] ?? [] as $index => $id) {
            $item['add_on_quantity_modes'][$index] = $this->addonMode($item['add_on_quantity_modes'][$index] ?? null,
                $item['add_on_qtys'][$index] ?? $item['quantity'], $item['quantity']);
        }
        foreach ($item['variations'] ?? [] as $key => $variation) {
            foreach ($variation['addons'] ?? [] as $index => $addon) {
                $item['variations'][$key]['addons'][$index]['quantity_mode'] = $this->addonMode(
                    $addon['quantity_mode'] ?? null, $addon['quantity'] ?? $item['quantity'], $item['quantity']);
            }
        }
        $item['quantity'] = $quantity;
        return $this->prepareItem($item);
    }

    /** Default quantities follow food; explicit customer choices stay independent. */
    public function prepareItem(array $item): array
    {
        $this->validateItem($item);
        $quantity = (int) $item['quantity'];
        $ids = $item['add_ons'] ?? [];
        foreach ($item['variations'] ?? [] as $variation) {
            foreach ($variation['addons'] ?? [] as $addon) {
                $ids[] = $addon['id'];
            }
        }
        $catalogue = count($ids) ? AddOn::whereIn('id', array_unique($ids))->get()->keyBy('id') : collect([]);
        $resolve = function ($id, $selectedQuantity, $mode) use ($catalogue, $quantity) {
            $addon = $catalogue->get($id);
            if (!$addon) {
                throw ValidationException::withMessages(['addon' => 'A selected add-on is no longer available. Remove it and select it again.']);
            }
            $selectedQuantity = $this->addonQuantity($selectedQuantity);
            $mode = $this->addonMode($mode, $selectedQuantity, $quantity);
            return ['id' => $addon->id, 'name' => $addon->name,
                'price' => $this->nonnegative($addon->price, 'addon_price'),
                'quantity' => $mode === 'food' ? $quantity : $selectedQuantity, 'quantity_mode' => $mode];
        };
        $selectedQuantities = $item['add_on_qtys'] ?? [];
        $modes = $item['add_on_quantity_modes'] ?? [];
        $item['addon_details'] = [];
        $item['add_on_qtys'] = [];
        $item['add_on_quantity_modes'] = [];
        $item['addon_price'] = 0;
        foreach ($item['add_ons'] ?? [] as $index => $id) {
            $addon = $resolve($id, $selectedQuantities[$index] ?? $quantity, $modes[$index] ?? null);
            $item['addon_details'][] = $addon;
            $item['add_on_qtys'][] = $addon['quantity'];
            $item['add_on_quantity_modes'][] = $addon['quantity_mode'];
            $item['addon_price'] += $addon['price'] * $addon['quantity'];
        }
        foreach ($item['variations'] ?? [] as $key => $variation) {
            foreach ($variation['addons'] ?? [] as $index => $addon) {
                $addon = $resolve($addon['id'], $addon['quantity'] ?? $quantity, $addon['quantity_mode'] ?? null);
                $item['variations'][$key]['addons'][$index] = $addon;
                $item['addon_price'] += $addon['price'] * $addon['quantity'];
            }
        }
        $this->validateItem($item);
        return $item;
    }

    /** Aggregate repeats across variation groups before checking or consuming stock. */
    public function addonQuantities(array $item): array
    {
        $quantities = [];
        $addons = $item['addon_details'] ?? [];
        foreach ($item['variations'] ?? [] as $variation) {
            $addons = array_merge($addons, $variation['addons'] ?? []);
        }
        foreach ($addons as $addon) {
            $quantities[$addon['id']] = ($quantities[$addon['id']] ?? 0) + $addon['quantity'];
        }
        return $quantities;
    }

    public function stockCheck($product, array $item, bool $increment = false)
    {
        $options = !empty($item['variation_option_ids']) ? explode(',', $item['variation_option_ids']) : [];
        $result = Helpers::addonAndVariationStockCheck($product, $item['quantity'], [], $options, [], $increment);
        if (data_get($result, 'out_of_stock') !== null) {
            return $result;
        }
        $quantities = $this->addonQuantities($item);
        if (!$quantities) {
            return null;
        }
        $addons = AddOn::whereIn('id', array_keys($quantities))->get()->values();
        $orderedQuantities = $addons->map(function ($addon) use ($quantities) {
            return $quantities[$addon->id];
        })->all();
        return Helpers::calculate_addon_price($addons, $orderedQuantities, $increment);
    }

    public function validateCart($cart): void
    {
        foreach (['tax', 'delivery_fee'] as $field) {
            if (isset($cart[$field])) {
                $this->nonnegative($cart[$field], $field);
            }
        }
        $net = 0;
        foreach ($cart as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            $this->validateItem($item);
            if (trim($item['is_deleted'] ?? 'N') !== 'Y') {
                $net += ($item['price'] - ($item['discount'] ?? 0)) * $item['quantity'] + ($item['addon_price'] ?? 0);
            }
        }
        $this->nonnegative(round($net - (new PosDiscountService())->amount($cart), 3), 'sale_total');
    }
}
