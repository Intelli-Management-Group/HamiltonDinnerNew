<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private Role $model
    ) {}

    public function findById($id): ?Role
    {
        return $this->model->find($id);
    }

    public function findWithPermissionsById($id): ?Role
    {
        return $this->model->withPermissions()->find($id);
    }

    public function queryWithPermissions(array $filters = []): Builder
    {
        // search() is a local scope defined in the Role model
        // for filtering based on search criteria.
        // Please refer to scopeSearch() in Role model for details.
        return $this->model->withPermissions()
            ->search($filters['search'] ?? null)
            ->latest();
    }

    public function paginateWithPermissions(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithPermissions($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAllWithPermissions(array $filters = []): Collection
    {
        return $this->queryWithPermissions($filters)->get();
    }

    public function findSoftDeletedByName(string $name): ?Role
    {
        return $this->model->withTrashed()
            ->where('name', $name)
            ->first();
    }

    public function nameConflictWithDeleted(string $name): ?Role
    {
        return $this->model->withTrashed()
            ->where('name', $name)
            ->whereNotNull('deleted_at')
            ->where('id', '<>', $excludingId)
            ->exists();
    }

    public function create(array $data): Role
    {
        return $this->model->create($data);
    }

    public function save(Role $role): Role
    {
        $role->save(); // Built-in Eloquent save method
        return $role;
    }

    public function delete(Role $role): bool
    {
        return (bool) $role->delete(); // Built-in Eloquent delete method
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model
            ->whereIn('id', $ids)
            ->delete();
    }
}