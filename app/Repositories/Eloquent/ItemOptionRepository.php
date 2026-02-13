<?php

namespace App\Repositories\Eloquent;

use App\Models\ItemOption;
use App\Repositories\Contracts\ItemOptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemOptionRepository implements ItemOptionRepositoryInterface
{
    public function __construct(
        private ItemOption $model
    ) {}

    public function findById($id): ?ItemOption
    {
        return $this->model->find($id);
    }

    public function queryWithCategoryId(array $filters = []): Builder
    {
        return $this->model
            ->categoryId($filters['cat_id'] ?? null)
            ->latest();
    }

    public function paginateWithCategoryId(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithCategoryId($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAllWithCategoryId(array $filters = []): Collection
    {
        return $this->queryWithCategoryId($filters)->get();
    }

    public function create(array $data): ItemOption
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): ItemOption
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(ItemOption $itemOption): ItemOption
    {
        $itemOption->save();
        return $itemOption;
    }

    public function delete(ItemOption $itemOption): bool
    {
        return (bool) $itemOption->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}