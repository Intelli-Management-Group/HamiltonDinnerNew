<?php

namespace App\Models;
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

    public function category()
    {
        return $this->belongsTo(CategoryDetail::class, 'cat_id');
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

}
