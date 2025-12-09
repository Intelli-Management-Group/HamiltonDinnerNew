<?php

namespace App\Repositories\Contracts;

use App\Models\Permission;

interface PermissionRepositoryInterface
{
    public function findById($id): ?Permission;
    public function getAllNames(): array;
    public function create(array $data): Permission;
    public function save(Permission $permission): Permission;
    public function delete(Permission $permission): bool;
}