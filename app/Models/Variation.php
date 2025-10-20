<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variation extends Model
{
    use HasFactory;

    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'id' => 'integer',
        'food_id' => 'integer',
        'min' => 'integer',
        'max' => 'integer',
        'is_required' => 'boolean',
        'link_addons' => 'boolean',
        'printing_option' => 'string',
    ];

    public function food()
    {
        return $this->belongsTo(Food::class, 'food_id');
    }
    public function variationOptions()
    {
        return $this->hasMany(VariationOption::class);
    }

}
