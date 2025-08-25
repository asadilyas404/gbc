<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
                $nextId = DB::table('translations')
                    ->select(DB::raw('NVL(MAX(id),0) + 1 as next_id'))
                    ->lockForUpdate()
                    ->value('next_id');

                $model->id = $nextId;
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
