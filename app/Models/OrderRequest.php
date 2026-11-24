<?php

namespace App\Models;

use App\Services\CallCenterSchema;
use Illuminate\Database\Eloquent\Model;

class OrderRequest extends Model
{
    protected $table = 'order_requests';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'restaurant_id' => 'integer',
        'zone_id' => 'integer',
        'preparation_minutes' => 'integer',
        'zone_delivery_minutes' => 'integer',
        'handover_minutes' => 'integer',
        'customer_eta_minutes' => 'integer',
        'driver_return_minutes' => 'integer',
        'delivery_fee' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'ready_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (self $request) {
            if (!$request->id) {
                $request->id = CallCenterSchema::nextId('order_requests_seq');
            }
        });
    }

    public function details()
    {
        return $this->hasMany(OrderRequestDetail::class, 'order_request_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function zone()
    {
        return $this->belongsTo(CallCenterZone::class, 'zone_id');
    }

    public function branch()
    {
        return $this->belongsTo(TblSoftBranch::class, 'restaurant_id', 'branch_id');
    }
}
