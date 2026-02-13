<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\FormMediaAttachments;
use App\Repositories\Contracts\Forms\FormMediaAttachmentsRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FormMediaAttachmentsRepository implements FormMediaAttachmentsRepositoryInterface
{
    public function __construct(
        private FormMediaAttachments $model
    ) {}

    public function findById($id): ?FormMediaAttachments
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

    public function create(array $data): FormMediaAttachments
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): FormMediaAttachments
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(FormMediaAttachments $formMediaAttachments): FormMediaAttachments
    {
        $formMediaAttachments->save();
        return $formMediaAttachments;
    }

    public function delete(FormMediaAttachments $formMediaAttachments): bool
    {
        return (bool) $formMediaAttachments->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}