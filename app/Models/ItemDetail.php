<?php

namespace App\Models;
use App\ItemOption;
use App\ItemPreference;
use App\Traits\FileUploadTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ItemDetail extends Model
{
    use SoftDeletes,FileUploadTrait;
    
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

    public function setItemImageAttribute($value)
    {
        $this->saveFile($value, 'item_image', "item_image/" . date('Y/m'));
    }

    public function getItemImageAttribute()
    {
        if (empty($this->attributes['item_image'])) {
            return null;
        } else {
            return $this->getFileUrl($this->attributes['item_image']);
        }
    }

    /**
     * Scope a query to retrieve item details by category ID.
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
