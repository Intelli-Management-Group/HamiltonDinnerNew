<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\FormResponse;
use App\Repositories\Contracts\Forms\FormResponseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FormResponseRepository implements FormResponseRepositoryInterface
{
    public function __construct(
        private FormResponse $model
    ) {}

    public function findById($id): ?FormResponse
    {
        return $this->model->find($id);
    }

    public function queryWithFormType(array $filters = []): Builder
    {
        return $this->model
            ->withFormType()
            ->orderBy('created_at', 'desc')
            ->latest();
    }

    public function paginateWithFormType(
        array $filters = [],
        int $perPage = 10,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithFormType($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAllWithFormType(array $filters = [], array $columns = ['*']): Collection
    {
        return $this->queryWithFormType($filters)->get($columns);
    }

    public function create(array $data): FormResponse
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): FormResponse
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(FormResponse $formResponse): FormResponse
    {
        $formResponse->save();
        return $formResponse;
    }

    public function delete(FormResponse $formResponse): bool
    {
        return (bool) $formResponse->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}