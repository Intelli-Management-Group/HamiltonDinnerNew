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
            return config('app.url') . "/images/user.webp";
        } else {
            return $this->getFileUrl($this->attributes['item_image']);
        }
    }
}
