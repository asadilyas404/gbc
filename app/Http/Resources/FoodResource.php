<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
    'id' => $this->id,
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

        $data['variations'][] = [
            'heading' => $variation->name ?? 'Unnamed',
            'type' => $variation->type ?? 'multi',
            'min' => $variation->type === 'multi' ? $variation->min : null,
            'max' => $variation->type === 'multi' ? $variation->max : null,
            'is_required' => $variation->is_required ?? false,
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







        // $originalPrice = $this->price;
        // $discount = $this->discount ?? 0;
        // $discountType = $this->discount_type ?? 'percent'; // assumes 'percent' or 'amount'
        
        // if ($discount > 0) {
        //     if ($discountType === 'percent') {
        //         $discountedPrice = $originalPrice - ($originalPrice * $discount / 100);
        //     } else { // fixed amount
        //         $discountedPrice = $originalPrice - $discount;
        //     }
        
        //     // Ensure price doesn't go negative
        //     $discountedPrice = max(0, $discountedPrice);
        
        //     $data = [
        //         'id' => $this->id,
        //         'name' => $this->name,
        //         'image' => url('storage/product/' . $this->image),
        //         'price' => round($discountedPrice, 2),
        //         'discount_price' => round($originalPrice, 2),
        //         'addons' => AddOnResource::collection($this->getAddOns()),
        //     ];
        // } else {
        //     // No discount
        //     $data = [
        //         'id' => $this->id,
        //         'name' => $this->name,
        //         'image' => url('storage/product/' . $this->image),
        //         'price' => round($originalPrice, 2),
        //         'discount_price' => 0,
        //         'addons' => AddOnResource::collection($this->getAddOns()),
        //     ];
        // }
        
//         $id = $request->input('id');
//         $food = Food::findOrFail($id);


//         $data['variations'] = $food->variations->map(function ($variation, $index) {
//             $heading = ucfirst($variation->name); // "Size", "Size 2", etc.
    
//             $options = $variation->variationOptions->map(function ($option) {
//                 return [
//                     'size' => $option->option_name,
//                     'price' => $option->option_price,
//                     'discount_price' => $option->discount_price ?? 0,
//                 ];
//             });
    
//             return [
//                 'heading' => $heading,
//                 'data' => $options,
//             ];
//         });
    
//         return response()->json($data);
    
        
        
        
//     }

//     private function getAddOns()
//     {
//         if (is_string($this->add_ons)) {
//             $ids = json_decode($this->add_ons, true);
//             if (is_array($ids)) {
//                 // Ensure integer and unique IDs
//                 $uniqueIds = array_unique(array_map('intval', $ids));
    
//                 // Fetch only once, then ensure unique collection by ID
//                 return \App\Models\AddOn::whereIn('id', $uniqueIds)
//                     ->get()
//                     ->unique('id')
//                     ->values(); // reindex to avoid gaps
//             }
//         }
    
//         return collect();
//     }
// }
