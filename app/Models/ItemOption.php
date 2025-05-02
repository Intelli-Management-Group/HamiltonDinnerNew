<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ItemOption extends Model
{
   
    use SoftDeletes;


    protected $fillable = [
        'option_name',
        'option_name_cn',
        'is_paid_item',
    ];

    protected $casts = [
        'cat_id' => 'integer',
        'is_allday' => 'boolean',
    ];


    function itemData()
    {
        return $this->hasOne('App\Models\ItemDetail', 'id', 'item_id');
    }

    public function options(){
        return $this->belongsTo(ItemOption::class);
    }

    public function preference(){
        return $this->belongsTo(ItemPreference::class);
    }
}

