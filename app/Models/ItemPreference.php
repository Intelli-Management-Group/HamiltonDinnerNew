<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ItemPreference extends Model
{
    protected $fillable = [
        'pname',
        'pname_cn'
    ];
}