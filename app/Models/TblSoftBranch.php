<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TblSoftBranch extends Model
{
    use HasFactory;

    protected $table = 'tbl_soft_branch';
    protected $primaryKey = 'branch_id';
}
