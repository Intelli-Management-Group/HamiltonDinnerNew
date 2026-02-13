<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function __construct(
        private Permission $model
    ) {}

    public function findById($id): ?Permission
    {
        return $this->model->find($id);
    }

    public function getAllNames(): array
    {
        return Permission::select('name')->pluck('name')->toArray();
    }

    public function queryWithRoles(array $filters = []): Builder
    {
        // search() is a local scope defined in the Permission model
        // for filtering based on search criteria.
        // Please refer to scopeSearch() in Permission model for details.
        return $this->model->withRoles()
            ->search($filters['search'] ?? null)
            ->latest();
    }

    public function paginateWithRoles(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithRoles($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAllWithRoles(array $filters = []): Collection
    {
        return $this->queryWithRoles($filters)->get();
    }

    public function create(array $data): Permission
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): Permission
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(Permission $permission): Permission
    {
        $permission->save(); // Built-in Eloquent save method
        return $permission;
    }

    public function delete(Permission $permission): bool
    {
        return $permission->delete(); // Built-in Eloquent delete method
    }
}