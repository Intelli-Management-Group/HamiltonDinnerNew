<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    public function __construct(
        Permission $model
    ) {
        parent::__construct($model);
    }

    public function getAllNames(): array
    {
        return Permission::select('name')->pluck('name')->toArray();
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        return $query->withRoles()
            ->search($filters['search'] ?? null)
            ->latest();
    }
}
