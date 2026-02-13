<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface RoleRepositoryInterface
{
    public function findById($id): ?Role;

    public function findWithPermissionsById($id): ?Role;

    public function queryWithPermissions(array $filters = []): Builder;

    public function paginateWithPermissions(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAllWithPermissions(array $filters = []): Collection;

    public function findSoftDeletedByName(string $name): ?Role;

    public function nameConflictWithDeleted(string $name): bool;

    public function create(array $data): Role;
    public function upsertByFilters(array $filters, array $data): Role;

    public function save(Role $role): Role;

    public function delete(Role $role): bool;

    public function bulkDeleteByIds(array $ids): int;
}