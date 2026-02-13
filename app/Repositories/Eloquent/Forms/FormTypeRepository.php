<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\FormType;
use App\Repositories\Contracts\Forms\FormTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FormTypeRepository implements FormTypeRepositoryInterface
{
    public function __construct(
        private FormType $model
    ) {}

    public function findById($id): ?FormType
    {
        return $this->model->find($id);
    }

    public function query(array $filters = []): Builder
    {
        return $this->model->orderByDesc('id');
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

    public function getAll(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    public function create(array $data): FormType
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): FormType
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(FormType $formType): FormType
    {
        $formType->save();
        return $formType;
    }

    public function delete(FormType $formType): bool
    {
        return (bool) $formType->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}