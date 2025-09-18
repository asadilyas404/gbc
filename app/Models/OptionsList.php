<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\ReportFilter;
use App\Scopes\ZoneScope;
class OptionsList extends Model
{
    use HasFactory,ReportFilter;

    protected $table = 'options_list';
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'status' => 'integer',
    ];

    protected $fillable = ['name', 'status'];

    protected $primaryKey   = 'id';

    public function translations()
    {
        return $this->morphMany(\App\Models\Translation::class, 'translationable');
    }

    public function getNameAttribute($value){
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'name') {
                    $translatedValue = $translation['value'];

                    // Ensure proper Unicode decoding for Oracle
                    if (!empty($translatedValue)) {
                        // Check if the value is properly encoded
                        if (!mb_check_encoding($translatedValue, 'UTF-8')) {
                            $translatedValue = mb_convert_encoding($translatedValue, 'UTF-8', 'auto');
                        }

                        // Handle any HTML entities that might have been encoded
                        $translatedValue = html_entity_decode($translatedValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }

                    return $translatedValue;
                }
            }
        }

        // Also ensure the main value is properly decoded
        if (!empty($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
            }
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);

        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function($query){
                return $query->where('locale', app()->getLocale());
            }]);
        });
    }

}
