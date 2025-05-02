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
            'image' => $this->image,
            'addons' => AddOnResource::collection($this->getAddOns()),
        ];
    
        // If this food has variations (assume a column `variations` as JSON)
        if ($this->variations && is_array(json_decode($this->variations, true))) {
            $data['variations'] = collect(json_decode($this->variations))->map(function ($var) {
                return [
                    'size' => $var->size ?? 'Standard',
                    'price' => $var->price ?? 0,
                    'discount_price' => $var->discount_price ?? 0
                ];
            });
        } else {
            // Otherwise just use standard pricing
            $data['price'] = $this->price;
            $data['discount_price'] = $this->discount_price;
        }
    
        return $data;
    }

    private function getAddOns()
{
    if (is_string($this->add_ons)) {
        $ids = json_decode($this->add_ons, true);
        return \App\Models\AddOn::whereIn('id', $ids)->get();
    }

    return [];
}

    
}
