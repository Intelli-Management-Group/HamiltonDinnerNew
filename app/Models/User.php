<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable implements JWTSubject

{

    use Notifiable,SoftDeletes,HasRoles;

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
        // get relation from

        // user.role_id >> role.id >> role_has_permissions.permission_id and role_has_permissions.role_id and get permission rows from permissions table

        return $this->hasManyThrough(
            Permission::class,
            RoleHasPermissions::class,
            'role_id', // Foreign key on RoleHasPermission table...
            'id', // Foreign key on Permission table...
            'role_id', // Local key on User table...
            'permission_id' // Local key on RoleHasPermission table...
        );
    }

    public function role(){
        return $this->hasOne(Role::class, 'id', 'role_id');
    }



}