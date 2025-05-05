<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FoodResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'image' => url('storage/product/' . $this->image),  // Assuming images are stored in public/storage/images/
            'price' => $this->price,
            'discount_price' => $this->discount_price ?? 0,  // Ensure this matches your DB column name
            'addons' => AddOnResource::collection($this->getAddOns()),
        ];

        if ($this->variationOptions && $this->variationOptions->count() > 0) {
            $data['variations'] = $this->variationOptions->map(function ($option) {
                return [
                    'size' => $option->option_name,
                    'price' => $option->option_price,
                    'discount_price' => $option->discount_price ?? 0,
                ];
            });
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
