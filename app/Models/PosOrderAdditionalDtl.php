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
    protected $casts = [
    'invoice_amount' => 'float',
    'cash_paid' => 'float',
    'card_paid' => 'float',
    'customer_name' => 'string',
    'car_number' => 'string',
    'phone' => 'string',
    'bank_account' => 'string',
];


}
