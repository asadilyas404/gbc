<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    // Use default Laravel behavior: auto-increment integer primary key `id`
    // and manage timestamps according to the migration definition.
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

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
