<?php

namespace App\Repositories\Contracts;

use App\Models\DateWiseOccupancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface DateWiseOccupancyRepositoryInterface
{
    public function findById($id): ?DateWiseOccupancy;

    public function query(array $filters = []): Builder;

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(array $filters = []): Collection;

    public function upsertByFilters(array $filters, array $data): DateWiseOccupancy;

    public function create(array $data): DateWiseOccupancy;

    public function save(DateWiseOccupancy $dateWiseOccupancy): DateWiseOccupancy;

    public function delete(DateWiseOccupancy $dateWiseOccupancy): bool;

    public function bulkDeleteByIds(array $ids): int;
}
