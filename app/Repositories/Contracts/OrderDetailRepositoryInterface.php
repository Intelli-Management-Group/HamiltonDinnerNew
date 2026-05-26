<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface OrderDetailRepositoryInterface extends BaseRepositoryInterface
{
    public function findOrderReportSummaries(string $date, array $ids): Collection;

    public function sumQuantityByDateAndItem(string $date, int $itemId): int;

    public function updateByFilters(array $filters, array $data): int;
}