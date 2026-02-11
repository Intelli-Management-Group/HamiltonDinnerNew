<?php

namespace App\Repositories\Contracts;

use App\Models\OrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface OrderDetailRepositoryInterface
{
    public function findById($id): ?OrderDetail;

    public function findOrderReportSummaries(string $date, array $ids): Collection;

    public function query(array $filters = []): Builder;

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(array $filters = []): Collection;

    public function create(array $data): OrderDetail;

    public function save(OrderDetail $orderDetail): OrderDetail;

    public function delete(OrderDetail $orderDetail): bool;

    public function bulkDeleteByIds(array $ids): int;
}