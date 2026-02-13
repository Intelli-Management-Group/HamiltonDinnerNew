<?php

namespace App\Repositories\Contracts;

use App\Models\ItemDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ItemDetailRepositoryInterface
{
    public function findById($id): ?ItemDetail;

    public function findOrderReportSummaries(
        array $ids, 
        array $columns = ['id', 'item_name', 'cat_id']
    ): Collection;

    public function queryWithCategoryId(array $filters = []): Builder;

    public function paginateWithCategoryId(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAllWithCategoryId(array $filters = []): Collection;

    public function create(array $data): ItemDetail;

    public function upsertByFilters(array $filters, array $data): ItemDetail;

    public function save(ItemDetail $itemDetail): ItemDetail;

    public function delete(ItemDetail $itemDetail): bool;

    public function bulkDeleteByIds(array $ids): int;
}