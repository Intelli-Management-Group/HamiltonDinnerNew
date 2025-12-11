<?php

namespace App\Repositories\Contracts;

use App\Models\CategoryDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface CategoryDetailRepositoryInterface
{
    public function findById($id): ?CategoryDetail;

    public function queryWithType(array $filters = []): Builder;

    public function paginateWithType(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAllWithType(array $filters = []): Collection;

    public function create(array $data): CategoryDetail;

    public function save(CategoryDetail $category): CategoryDetail;

    public function delete(CategoryDetail $category): bool;

    public function bulkDeleteByIds(array $ids): int;
}