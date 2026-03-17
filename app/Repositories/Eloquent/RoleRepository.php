<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    protected const ALLOWED_RELATIONS = ['permissionList'];

    public function __construct(
        Role $model
    ) {
        parent::__construct($model);
    }

    public function findSoftDeletedByName(string $name): ?Role
    {
        return $this->model->withTrashed()
            ->where('name', $name)
            ->first();
    }

    public function nameConflictWithDeleted(string $name): bool
    {
        return $this->model->withTrashed()
            ->where('name', $name)
            ->whereNotNull('deleted_at')
            ->exists();
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        return $query->withPermissions()
            ->search($filters['search'] ?? null)
            ->latest();
    }
}