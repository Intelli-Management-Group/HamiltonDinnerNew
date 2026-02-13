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

    /** 
     * Find MenuDetail by date.
     * 
     * @param string $date
     * @return MenuDetail|null
     */
    public function findByDate(string $date): ?MenuDetail
    {
        return $this->model
            ->whereDate('date', $date) // whereDate is from Eloquent's query builder
            ->first();
    }

    /** Find most recent MenuDetail.
     * 
     * @return MenuDetail|null
     */
    public function findLatest(): ?MenuDetail
    {
        return $this->model
            ->orderBy('date', 'desc')
            ->first();
    }

    /** 
     * Find most recent MenuDetail date.
     * 
     * @return string|null
     */
    public function findLatestDate(): ?string
    {
        return optional($this->findLatest())->date;
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

    public function getAll(
        array $filters = [],
        array $columns = ['*']
    ): Collection
    {
        return $this->query($filters)->get($columns);
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

    public function upsertByFilters(array $filters, array $data): MenuDetail
    {
        return $this->model->updateOrCreate($filters, $data);
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