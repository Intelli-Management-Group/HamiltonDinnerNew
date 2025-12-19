<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class CategoryDetail extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'cat_name',
        'category_chinese_name',
        'type',
        'parent_id',
    ];


    function parentId()
    {
        return $this->hasOne('App\Models\CategoryDetail', 'parent_id', 'id');
    }
    
     function catParentId()
    {
        return $this->belongsTo('App\Models\CategoryDetail', 'parent_id');
    }
    
    public function setParentIdAttribute($value){
        $this->attributes['parent_id'] = is_null($value) ? 0 : $value;
    }
    
    /**
     * Scope a query to retrieve parent categories.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeParent($query)
    {
        return $query->where('parent_id',0);
    }

    /**
     * Scope a query to retrieve categories by type.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeType($query, $type)
    {
        if ($type === null || $type === '') {
            return $query;
        }
        
        return $query->where('type', $type);
    }
}
