<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaleCustomer extends Model
{
    protected $table = 'tbl_sale_customer';
    protected $primaryKey = 'customer_id';
    public $incrementing = false;

    protected $fillable = [
        'customer_code',
        'customer_type',
        'customer_name',
        'customer_mobile_no',
        'customer_email',
        'business_id',
        'company_id',
        'branch_id',
        'customer_id'
    ];

    protected $casts = [
        'business_id' => 'integer',
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'customer_id' => 'integer'
    ];

    public $timestamps = true;

    /**
     * Generate the next customer code
     */
    public static function generateCustomerCode()
    {
        $lastCustomer = self::orderBy(DB::raw('customer_code'), 'desc')->first();

        if ($lastCustomer && $lastCustomer->customer_code) {
            // Extract number from existing code (e.g., CU-0002877 -> 2877)
            preg_match('/CU-(\d+)/', $lastCustomer->customer_code, $matches);
            $nextNumber = isset($matches[1]) ? (int)$matches[1] + 1 : 1;
        } else {
            $nextNumber = 1;
        }

        return 'CU-' . str_pad($nextNumber, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Generate the next global customer ID
     */
    public static function generateCustomerId($restaurantId)
    {
        return \App\CentralLogics\Helpers::generateGlobalId($restaurantId);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'customer_id');
    }
}
