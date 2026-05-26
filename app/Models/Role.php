<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    
    use softDeletes;

    protected $attributes = [
        'guard_name' => 'api',
    ];

    protected $fillable = [
        'name',
    ];

    public function permissionList()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }

    /**
     * Scope a query to retrieve roles with the permissions they grant.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPermissions($query)
    {
        return $query->with('permissionList');
    }

    /**
     * Scope a query to retrieve roles with a matching name
     * to the search term, if it is provided. Otherwise, return the unmodified query.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $searchTerm)
    {
        if (!$searchTerm) return $query;

        return $query->where('name', 'LIKE', "%{$searchTerm}%");
    }

    // public function userList(){
    //     return $this->hasMany(BackendUser::class , "role_id" , "id");
    // }
}