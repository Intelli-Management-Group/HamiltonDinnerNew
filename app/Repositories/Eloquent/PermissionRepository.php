<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;

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

    public function create(array $data): Permission
    {
        return $this->model->create($data);
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