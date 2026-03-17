<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    
    use softDeletes;


    protected $attributes = [
        'guard_name' => 'api',
    ];

    protected $table = 'permissions';

    public function rolesList()
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions', 'permission_id', 'role_id');
    }

    /**
     * Scope a query to retrieve permissions with their roles.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithRoles($query)
    {
        return $query->with('rolesList');
    }

    /**
     * Scope a query to retrieve permissions with a matching name or display_name
     * to the search term, if it is provided. Otherwise, return the unmodified query
     */
    public function scopeSearch($query, $searchTerm)
    {
        if (!$searchTerm) return $query;
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%")
              ->orWhere('display_name', 'LIKE', "%{$searchTerm}%");
        });
    }
}