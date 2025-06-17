<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
// use MatanYadaev\EloquentSpatial\Traits\HasSpatial;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Builder;

class KitchenOrderStatusLog extends Model
{

    protected $fillable = [
        'status',
        'order_id'
    ];

    public function order() {
        return $this->belongsTo(Order::class,'order_id', 'id')
        ->where('global_id', $this->global_id);
    }
}
