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
                    return $translation['value'];
                }
            }
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

    protected static function boot()
    {
        parent::boot();

        // Auto-delete translations when option is deleted
        static::deleting(function ($option) {
            $option->translations()->delete();
        });
    }

}
