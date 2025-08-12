<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class VendorEmployee extends Authenticatable
{
    use Notifiable;

    public $incrementing = false;
    protected $primaryKey = 'id';
    // protected $table = 'users';

    protected $hidden = [
        'password',
        'auth_token',
        'remember_token',
    ];
    protected $fillable = ['id','f_name','l_name','phone','email','image','employee_role_id','vendor_id','restaurant_id','password','status','remember_token','created_at','updated_at','is_pushed'];

    protected $appends = ['image_full_url'];
    public function getImageFullUrlAttribute(){
        $value = $this->image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('vendor',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('vendor',$value,'public');
    }
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function role(){
        return $this->belongsTo(EmployeeRole::class,'employee_role_id');
    }

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    protected static function booted()
    {
        // static::addGlobalScope('storage', function ($builder) {
        //     $builder->with('storage');
        // });

    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if($model->isDirty('image')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }
}
