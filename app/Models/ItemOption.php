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
        'is_paid_item' => 'boolean',
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

    /**
     * Scope a query to retrieve item options by category ID.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCategoryId($query, $catId)
    {
        if ($catId === null || $catId === '') {
            return $query;
        }
        
        return $query->where('cat_id', $catId);
    }
}