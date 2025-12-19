<?php

namespace App\Repositories\Eloquent;

use App\Models\ItemDetail;
use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemDetailRepository implements ItemDetailRepositoryInterface
{
    public function __construct(
        private ItemDetail $model
    ) {}

    public function findById($id): ?ItemDetail
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

    public function create(array $data): ItemDetail
    {
        return $this->model->create($data);
    }

    public function save(ItemDetail $itemDetail): ItemDetail
    {
        $itemDetail->save();
        return $itemDetail;
    }

    public function delete(ItemDetail $itemDetail): bool
    {
        return (bool) $itemDetail->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}