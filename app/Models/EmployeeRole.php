<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;


class EmployeeRole extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;

    protected $fillable = ['id','name','modules','status','restaurant_id','created_at','updated_at','is_pushed'];

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function getNameAttribute($value){
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'name') {
                    return $translation['value'];
                }
            }
        }

        return $value;
    }

    protected static function booted()
    {
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function($query){
                return $query->where('locale', app()->getLocale());
            }]);
        });
    }
}
