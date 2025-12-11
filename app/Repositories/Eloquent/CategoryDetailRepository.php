<?php

namespace App\Repositories\Eloquent;

use App\Models\CategoryDetail;
use App\Repositories\Contracts\CategoryDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CategoryDetailRepository implements CategoryDetailRepositoryInterface
{
    public function __construct(
        private CategoryDetail $model
    ) {}

    public function findById($id): ?CategoryDetail
    {
        return $this->model->find($id);
    }

    public function queryWithType(array $filters = []): Builder
    {
        return $this->model
            ->type($filters['type'] ?? null)
            ->latest();
    }

    public function paginateWithType(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithType($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAllWithType(array $filters = []): Collection
    {
        return $this->queryWithType($filters)->get();
    }

    public function create(array $data): CategoryDetail
    {
        return $this->model->create($data);
    }

    public function save(CategoryDetail $category): CategoryDetail
    {
        $category->save();
        return $category;
    }

    public function delete(CategoryDetail $category): bool
    {
        return (bool) $category->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}