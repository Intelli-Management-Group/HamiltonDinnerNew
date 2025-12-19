<?php

namespace App\Repositories\Contracts;

use App\Models\ItemOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ItemOptionRepositoryInterface
{
    public function findById($id): ?ItemOption;

    public function queryWithCategoryId(array $filters = []): Builder;

    public function paginateWithCategoryId(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAllWithCategoryId(array $filters = []): Collection;

    public function create(array $data): ItemOption;

    public function save(ItemOption $itemOption): ItemOption;

    public function delete(ItemOption $itemOption): bool;

    public function bulkDeleteByIds(array $ids): int;
}