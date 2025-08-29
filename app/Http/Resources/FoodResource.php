<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\AddOn;

class FoodResource extends JsonResource
{
    public function toArray($request)
    {
        $originalPrice = $this->price;
        $discount = $this->discount ?? 0;
        $discountType = $this->discount_type ?? 'percent'; // 'percent' or 'amount'

        $discountAmount = 0;

        if ($discount > 0) {
            if ($discountType === 'percent') {
                $discountAmount = ($originalPrice * $discount) / 100;
            } else {
                $discountAmount = $discount;
            }

            $discountedPrice = max(0, $originalPrice - $discountAmount);
            $price = round($discountedPrice, 2);
        } else {
            $price = round($originalPrice, 2);
            $discountType = 'percent'; // default value when no discount
        }

        $data = [
            'id' => (int) $this->id,
            'name' => $this->name,
            'image' => url('storage/product/' . $this->image),
            'price' => $price,
            'discount_price' => (double) round($discountAmount, 2),
            'discount_type' => $discountType,
            'available_time_starts' => $this->available_time_starts,
            'available_time_ends' => $this->available_time_ends,
            'addons' => AddOnResource::collection($this->getAddOns()),
        ];

        if ($this->variationOptions && $this->variationOptions->count() > 0) {
            $data['variations'] = [];

            // Group variationOptions by variation_id
            $grouped = $this->variationOptions->groupBy('variation_id');

            foreach ($grouped as $variationId => $options) {
                $variation = optional($options->first()->variation); // Get the Variation model

                // Get addons for this variation if link_addons is enabled
                $variationAddons = collect();
                if ($variation && $variation->link_addons) {
                    $addonIds = json_decode($this->add_ons, true);
                    if (is_array($addonIds)) {
                        $variationAddons = AddOn::whereIn('id', $addonIds)
                            ->active()
                            ->get()
                            ->map(function ($addon) {
                                return [
                                    'id' => (int) $addon->id,
                                    'name' => $addon->name,
                                    'price' => $addon->price,
                                ];
                            });
                    }
                }

                $data['variations'][] = [
                    'heading' => $variation->name ?? 'Unnamed',
                    'type' => $variation->type ?? 'multi',
                    'min' => $variation->type === 'multi' ? $variation->min : null,
                    'max' => $variation->type === 'multi' ? $variation->max : null,
                    'is_required' => $variation->is_required ?? false,
                    'link_addons' => $variation->link_addons ?? false,
                    'addons' => $variationAddons,
                    'data' => $options->map(function ($option) {
                        return [
                            'option_name' => $option->option_name,
                            'option_price' => $option->option_price,
                        ];
                    })->values(),
                ];
            }
        }

        return $data;
    }

    private function getAddOns()
    {
        if (is_string($this->add_ons)) {
            $ids = json_decode($this->add_ons, true);
            if (is_array($ids)) {
                // Ensure integer and unique IDs
                $uniqueIds = array_unique(array_map('intval', $ids));

                // Fetch only once, then ensure unique collection by ID
                return \App\Models\AddOn::whereIn('id', $uniqueIds)
                    ->get()
                    ->unique('id')
                    ->values(); // reindex to avoid gaps
            }
        }

        return collect();
    }
}

