<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ItemDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
       
        'cat_id',
        'item_name',
        'item_chinese_name',
        'is_allday',
        'item_image',
        'options',
        'preference',
     
    ];


    function categoryData()
    {
        return $this->hasOne('App\Models\CategoryDetail', 'id', 'cat_id');
    }


    public function options(){
        return $this->belongsTo(ItemOption::class);
    }
    
    
    public function preference(){
        return $this->belongsTo(ItemPreference::class);
    }
}