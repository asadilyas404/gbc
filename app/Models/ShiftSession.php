<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Scopes\RestaurantScope;
use App\Scopes\ZoneScope;
use Illuminate\Support\Facades\DB;

class ShiftSession extends Model
{
    protected $table = 'shift_sessions';
    protected $primaryKey = 'session_id';
    public $incrementing = false;

    protected $fillable = [
        'session_id',
        'shift_id',
        'start_date',
        'end_date',
        'opening_cash',
        'closing_cash',
        'opening_visa',
        'closing_visa',
        'company_id',
        'branch_id',
        'business_id',
        'user_id',
        'session_no',
        'session_status'
    ];

    protected $casts = [
        'session_id' => 'string',
        'shift_id' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'opening_cash' => 'float',
        'closing_cash' => 'float',
        'opening_visa' => 'float',
        'closing_visa' => 'float',
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'business_id' => 'integer',
        'user_id' => 'integer',
        'session_no' => 'integer',
        'session_status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'business_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('session_status', 'open');
    }

    public function scopeCurrent($query)
    {
        return $query->where('session_status', 'open')
                    ->where('business_id', Helpers::get_restaurant_id());
    }

    protected static function booted()
    {
        if(auth('vendor')->check() || auth('vendor_employee')->check())
        {
            static::addGlobalScope(new RestaurantScope);
        }
        static::addGlobalScope(new ZoneScope);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shiftSession) {
            // Generate session_id using the same pattern as orders
            $shiftSession->session_id = Helpers::generateGlobalId($shiftSession->business_id);

            // Set default values
            $shiftSession->company_id = 1;
            $shiftSession->business_id = $shiftSession->business_id ?? Helpers::get_restaurant_id();
            $shiftSession->user_id = auth('vendor')->id() ?? auth('vendor_employee')->id();

            // Generate session number
            $lastSession = static::where('business_id', $shiftSession->business_id)
                                ->orderBy('session_no', 'desc')
                                ->first();
            $shiftSession->session_no = $lastSession ? $lastSession->session_no + 1 : 1;

            // Set status to open when creating
            $shiftSession->session_status = 'open';
        });
    }

    // Helper method to get shift name from tbl_defi_shift
    public function getShiftNameAttribute()
    {
        $shift = DB::table('tbl_defi_shift')
                    ->where('shift_id', $this->shift_id)
                    ->first();
        return $shift ? $shift->shift_name : 'Unknown Shift';
    }

    // Helper method to check if session is open
    public function isOpen()
    {
        return $this->session_status === 'open';
    }

    // Helper method to close session
    public function closeSession($closingData = [])
    {
        $this->session_status = 'close';
        $this->end_date = now();

        if (!empty($closingData)) {
            $this->fill($closingData);
        }

        return $this->save();
    }
}
