<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Translation extends Model
{
    // protected $primaryKey = 'id';
    public $incrementing = false;
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

    // Accessor to ensure proper Unicode decoding when retrieving values
    public function getValueAttribute($value)
    {
        if (!empty($value)) {
            // Check if the value is properly encoded
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
            }

            // Handle any HTML entities that might have been encoded
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }

    // Mutator to ensure proper Unicode encoding when setting values
    public function setValueAttribute($value)
    {
        if (!empty($value)) {
            // Ensure the string is properly encoded as UTF-8
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
            }

            // For Oracle, ensure proper Unicode handling
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $this->attributes['value'] = $value;
    }

    public function translationable()
    {
        return $this->morphTo();
    }
}
