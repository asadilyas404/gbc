<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOrderAdditionalDtl extends Model
{
    protected $table = 'pos_order_additional_dtl';

    public function order()
    {
        return $this->belongsTo(Order::class,'order_id');
    }
    public function vendor()
    {
        return $this->order->restaurant();
    }

}
