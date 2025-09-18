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

            // For Oracle CLOB, we need to handle Unicode specially
            // Convert to UTF-8 and ensure proper encoding for CLOB
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

            // For Oracle CLOB, we might need to use raw SQL for proper Unicode handling
            $this->attributes['value'] = $value;
        } else {
            $this->attributes['value'] = $value;
        }
    }

    public function translationable()
    {
        return $this->morphTo();
    }

    // Custom method to handle CLOB inserts with proper Unicode support
    public static function createWithClob($data)
    {
        try {
            // Use raw SQL for CLOB to ensure proper Unicode handling
            $id = DB::table('translations')
                ->select(DB::raw('NVL(MAX(id),0) + 1 as next_id'))
                ->lockForUpdate()
                ->value('next_id');

            $value = $data['value'] ?? '';

            // Ensure proper UTF-8 encoding for CLOB
            if (!empty($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
            }

            // Use raw SQL with proper CLOB handling
            DB::statement("
                INSERT INTO translations (id, translationable_type, translationable_id, locale, key, value, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $id,
                $data['translationable_type'],
                $data['translationable_id'],
                $data['locale'],
                $data['key'],
                $value,
                now(),
                now()
            ]);

            return static::find($id);
        } catch (\Exception $e) {
            \Log::error("CLOB insert error: " . $e->getMessage());
            return false;
        }
    }

    // Custom method to update CLOB with proper Unicode support
    public function updateWithClob($data)
    {
        try {
            $value = $data['value'] ?? '';

            // Ensure proper UTF-8 encoding for CLOB
            if (!empty($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
            }

            // Use raw SQL with proper CLOB handling
            DB::statement("
                UPDATE translations
                SET value = ?, updated_at = ?
                WHERE id = ?
            ", [$value, now(), $this->id]);

            return true;
        } catch (\Exception $e) {
            \Log::error("CLOB update error: " . $e->getMessage());
            return false;
        }
    }
}
