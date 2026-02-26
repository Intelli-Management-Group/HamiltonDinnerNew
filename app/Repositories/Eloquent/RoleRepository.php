<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    public function __construct(
        Role $model
    ) {
        parent::__construct($model);
    }

    public function findWithPermissionsById($id): ?Role
    {
        return $this->model->withPermissions()->find($id);
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