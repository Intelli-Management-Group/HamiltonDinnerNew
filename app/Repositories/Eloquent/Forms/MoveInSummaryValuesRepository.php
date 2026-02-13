<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\MoveInSummaryValues;
use App\Repositories\Contracts\Forms\MoveInSummaryValuesRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MoveInSummaryValuesRepository implements MoveInSummaryValuesRepositoryInterface
{
    public function __construct(
        private MoveInSummaryValues $model
    ) {}

    public function findById($id): ?MoveInSummaryValues
    {
        return $this->model->find($id);
    }

    public function query(array $filters = []): Builder
    {
        return $this->model->latest();
    }

    public function paginate(
        array $filters = [],
        int $perPage = 10,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAll(array $filters = [], array $columns = ['*']): Collection
    {
        return $this->query($filters)->get($columns);
    }

    public function create(array $data): MoveInSummaryValues
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): MoveInSummaryValues
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(MoveInSummaryValues $moveInSummaryValues): MoveInSummaryValues
    {
        $moveInSummaryValues->save();
        return $moveInSummaryValues;
    }

    public function delete(MoveInSummaryValues $moveInSummaryValues): bool
    {
        return (bool) $moveInSummaryValues->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}