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

        // Removed ID assignment - Oracle trigger handles this via translations_id_seq
        // Laravel was causing conflicts by also trying to assign IDs
        // Oracle trigger: TRANSLATIONS_ID_TRG sets ID using sequence
    }

    protected $casts = [
        'translationable_id' => 'integer',
    ];


    public function translationable()
    {
        return $this->morphTo();
    }
}
