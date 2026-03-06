<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    public function findById(
        int $id,
        array $filters = [],
        array $relations = []
    ): ?Model;

    public function query(
        array $filters = [],
        array $relations = []
    ): Builder;

    public function paginate(
        array $filters = [],
        array $relations = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(
        array $filters = [],
        array $relations = [],
        array $columns = ['*']
    ): Collection;

    public function create(array $data): Model;

    public function update(int $id, array $data): ?Model;

    public function upsertByFilters(array $filters, array $data): Model;

    public function save(Model $model): Model;

    public function delete(Model $model): bool;

    public function bulkDeleteByIds(array $ids): int;
}
