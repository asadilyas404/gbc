<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOrderAdditionalDtl extends Model
{
    use HasFactory;
    protected $table = 'pos_order_additional_dtl';

    protected $fillable = [
        'order_id',
        'restaurant_id',
        'customer_name',
        'car_number',
        'phone',
        'invoice_amount',
        'cash_paid',
        'card_paid',
        'bank_account',
        'credit_paid'
    ];

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
