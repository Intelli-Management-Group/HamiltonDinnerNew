<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class OrderDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'room_id', 'date', 'item_id', 'quantity', 'comment', 'status', 'is_for_guest',
        'is_brk_tray_service', 'is_lunch_tray_service', 'is_dinner_tray_service',
        'is_brk_escort_service', 'is_lunch_escort_service', 'is_dinner_escort_service',
        'is_brk_takeout_service', 'is_lunch_takeout_service', 'is_dinner_takeout_service',
        'item_options', 'preference',
    ];

    function roomData()
    {
        return $this->hasOne('App\Models\RoomDetail', 'room_id', 'room_id');
    }

    function itemData()
    {
        return $this->hasOne('App\Models\ItemDetail', 'id', 'item_id');
    }
}
