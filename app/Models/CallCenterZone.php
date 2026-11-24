<?php

namespace App\Models;

use App\Services\CallCenterSchema;
use Illuminate\Database\Eloquent\Model;

class CallCenterZone extends Model
{
    protected $table = 'call_center_zones';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'restaurant_id' => 'integer',
        'delivery_minutes' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (self $zone) {
            if (!$zone->id) {
                $zone->id = CallCenterSchema::nextId('call_center_zones_seq');
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(TblSoftBranch::class, 'restaurant_id', 'branch_id');
    }
}
