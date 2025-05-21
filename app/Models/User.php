<?php

namespace App\Models;

use App\Traits\FileUploadTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable implements JWTSubject

{

    use Notifiable,SoftDeletes,HasRoles,FileUploadTrait;

    // Rest omitted for brevity

    public $timestamps = true;
    
    protected $dates = ['deleted_at'];

    public $hidden = [
        'email_verified_at', 
        'password', 
        'remember_token',
    ];

    public $fillable = [
        "name",
        "user_name",
        "email",
        "password",
        "email_verified_at",
        "avatar",
        "role_id",
        "role",
        "settings",
        'is_admin'
        
    ];


    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    
     public function permissionList()
    {
        return $this->hasManyThrough(
            Permission::class,
            RoleHasPermissions::class,
            'role_id', 
            'id', 
            'role_id', 
            'permission_id'
        );
    }

    public function role(){
        return $this->hasOne(Role::class, 'id', 'role_id');
    }

    public function setAvatarAttribute($value)
    {
        $this->saveFile($value, 'avatar', "user/" . date('Y/m'));
    }

    public function getAvatarAttribute()
    {
        if (empty($this->attributes['avatar'])) {
            return config('app.url') . "/images/user.webp";
        } else {
            return $this->getFileUrl($this->attributes['avatar']);
        }
    }



}