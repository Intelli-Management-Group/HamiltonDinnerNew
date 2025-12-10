<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function findById($id): ?User;
    public function findWithPermissionsById($id): ?User;
    public function queryWithPermissions(array $filters = []): Builder;
    public function paginateWithPermissions(
        array $filters = [],
        int $perPage = 15,
        int $page = 1
    ): LengthAwarePaginator;
    public function getAllWithPermissions(array $filters = []): Collection;
    public function findSoftDeletedById($id): ?User;
    public function create(array $data): User;
    public function save(User $user): User;
    public function delete(User $user): bool;
    public function bulkDeleteByIds(array $ids): int;
}