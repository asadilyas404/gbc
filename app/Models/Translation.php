<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'translationable_type',
        'translationable_id',
        'locale',
        'key',
        'value',
    ];

    protected $casts = [
        'translationable_id' => 'integer',
    ];


    public function translationable()
    {
        return $this->morphTo();
    }
}
