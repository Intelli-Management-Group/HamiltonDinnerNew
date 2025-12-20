<?php

namespace App\Repositories\Eloquent;

use App\Models\MenuDetail;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MenuDetailRepository implements MenuDetailRepositoryInterface
{
    public function __construct(
        private MenuDetail $model
    ) {}

    public function findById($id): ?MenuDetail
    {
        return $this->model->find($id);
    }

    public function query(array $filters = []): Builder
    {
        return $this->model->latest();
    }

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAll(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    public function findSoftDeletedByDate(string $date): ?MenuDetail
    {
        return $this->model->withTrashed()
            ->where('date', $date)
            ->first();
    }

    public function create(array $data): MenuDetail
    {
        return $this->model->create($data);
    }

    public function save(MenuDetail $menuDetail): MenuDetail
    {
        $menuDetail->save();
        return $menuDetail;
    }

    public function delete(MenuDetail $menuDetail): bool
    {
        return $menuDetail->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}