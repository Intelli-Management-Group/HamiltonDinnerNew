<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class RoomDetail extends Model
{
    use SoftDeletes;

    // public function setPasswordAttribute($value){
    //     dd(dcrypt($value));
    //     $this->attributes['password'] = md5($value);
    //     dd($this->attributes);
    // }
    
    protected $fillable = [
        'room_name',
        'special_instrucations',
        'occupancy',
        'resident_name',
        'language',
        'is_active',
        'password',
        'role_id',
        'food_texture'
    ];

    /** 
     * Scope a query to only include active room details.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $isActive
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsActive($query, $isActive)
    {
        if ($isActive === null || $isActive === '') {
            return $query;
        }

        return $query->where('is_active', $isActive);
    }
}
