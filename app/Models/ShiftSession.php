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
        'session_status',
        'closing_incharge',
        'verified'
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
        'closing_incharge' => 'integer',
        'verified' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'closing_incharge', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'session_id');
    }

    public function scopeActive($query)
    {
        return $query->where('session_status', 'open');
    }

    public function scopeCurrent($query)
    {
        return $query->where('session_status', 'open')
                    ->where('branch_id', Helpers::get_restaurant_id());
    }

    protected static function booted()
    {
        // Apply restaurant filtering manually since we use branch_id instead of restaurant_id
        if(auth('vendor')->check() || auth('vendor_employee')->check())
        {
            static::addGlobalScope('restaurant', function (Builder $builder) {
                $builder->where('branch_id', Helpers::get_restaurant_id());
            });
        }
        static::addGlobalScope(new ZoneScope);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shiftSession) {
            // Set default values
            $shiftSession->company_id = 1;
            $shiftSession->business_id = 1;
            $shiftSession->branch_id = $shiftSession->branch_id ?? Helpers::get_restaurant_id();
            $shiftSession->user_id = auth('vendor')->id() ?? auth('vendor_employee')->id();

            $shiftSession->session_id = Helpers::generateGlobalId($shiftSession->branch_id);

            $lastSession = static::where('branch_id', $shiftSession->branch_id)
                                ->orderBy('session_no', 'desc')
                                ->first();
            $shiftSession->session_no = $lastSession ? $lastSession->session_no + 1 : 1;

            $shiftSession->session_status = 'open';
        });
    }

    public function getShiftNameAttribute()
    {
        $shift = DB::table('tbl_defi_shift')
                    ->where('shift_id', $this->shift_id)
                    ->first();
        return $shift ? $shift->shift_name : 'Unknown Shift';
    }

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
