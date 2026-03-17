<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ItemDetailRepositoryInterface extends BaseRepositoryInterface
{
    public function findOrderReportSummaries(
        array $ids, 
        array $columns = ['id', 'item_name', 'cat_id']
    ): Collection;

    public function findByIdsAndParentFlag(
        array $ids,
        bool $isParent
    ): Collection;

    public function findByCategoryWithParentId(int $categoryId): Collection;
}