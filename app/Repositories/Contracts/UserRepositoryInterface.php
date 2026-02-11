<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function findById($id): ?User;

    public function query(
        array $filters = [],
        array $relations = [],
    ): Builder;

    public function paginate(
        array $filters = [],
        array $relations = [],
        int $perPage = 15,
        int $page = 1
    ): LengthAwarePaginator;

    public function getAll(
        array $filters = [],
        array $relations = []
    ): Collection;

    public function findSoftDeletedById($id): ?User;

    public function create(array $data): User;

    public function save(User $user): User;

    public function delete(User $user): bool;
    
    public function bulkDeleteByIds(array $ids): int;
}