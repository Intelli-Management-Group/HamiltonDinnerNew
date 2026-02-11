<?php

namespace App\Repositories\Contracts;

use App\Models\MenuDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface MenuDetailRepositoryInterface
{
    public function findById($id): ?MenuDetail;

    public function findByDate(string $date): ?MenuDetail;

    public function findLatest(): ?MenuDetail;

    public function findLatestDate(): ?string;

    public function query(array $filters = []): Builder;

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(array $filters = []): Collection;

    public function findSoftDeletedByDate(string $date): ?MenuDetail;

    public function create(array $data): MenuDetail;

    public function save(MenuDetail $menuDetail): MenuDetail;

    public function delete(MenuDetail $menuDetail): bool;

    public function bulkDeleteByIds(array $ids): int;
}