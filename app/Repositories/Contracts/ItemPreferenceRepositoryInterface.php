<?php

namespace App\Repositories\Contracts;

use App\Models\ItemPreference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ItemPreferenceRepositoryInterface
{
    public function findById($id): ?ItemPreference;

    public function query(array $filters = []): Builder;

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(array $filters = []): Collection;

    public function create(array $data): ItemPreference;

    public function upsertByFilters(array $filters, array $data): ItemPreference;

    public function save(ItemPreference $itemPreference): ItemPreference;

    public function delete(ItemPreference $itemPreference): bool;

    public function bulkDeleteByIds(array $ids): int;
}