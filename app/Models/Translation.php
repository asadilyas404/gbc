<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false; // manual incrementing for Oracle compatibility
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'translationable_type',
        'translationable_id',
        'locale',
        'key',
        'value',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $maxExistingId = (int) static::max('id');
                $model->id = $maxExistingId > 0 ? $maxExistingId + 1 : 1;
            }
        });
    }

    protected $casts = [
        'translationable_id' => 'integer',
    ];


    public function translationable()
    {
        return $this->morphTo();
    }
}
