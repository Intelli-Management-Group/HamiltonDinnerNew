<?php

namespace App\Repositories\Contracts;

use App\Models\Role;

interface RoleRepositoryInterface extends BaseRepositoryInterface
{
    public function findWithPermissionsById($id): ?Role;

    public function findSoftDeletedByName(string $name): ?Role;

    public function nameConflictWithDeleted(string $name): bool;
}