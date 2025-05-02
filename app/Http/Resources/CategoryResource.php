<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'foods' => FoodResource::collection(
                \App\Models\Food::where('category_id', $this->id)->get()
            )
        ];
    }
}
