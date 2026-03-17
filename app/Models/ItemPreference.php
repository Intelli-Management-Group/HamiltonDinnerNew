<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ItemPreference extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pname',
        'pname_cn'
    ];
}