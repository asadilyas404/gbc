<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\ReportFilter;
class OptionsList extends Model
{
    use HasFactory,ReportFilter;

    protected $table = 'options_list';
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
    ];


    protected $primaryKey   = 'id';

}
