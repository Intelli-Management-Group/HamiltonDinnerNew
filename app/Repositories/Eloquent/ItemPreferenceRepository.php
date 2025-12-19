<?php

namespace App\Repositories\Eloquent;

use App\Models\ItemPreference;
use App\Repositories\Contracts\ItemPreferenceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemPreferenceRepository implements ItemPreferenceRepositoryInterface
{
    public function __construct(
        private ItemPreference $model
    ) {}

    public function findById($id): ?ItemPreference
    {
        return $this->model->find($id);
    }

    public function query(array $filters = []): Builder
    {
        return $this->model
            ->latest();
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

    public function create(array $data): ItemPreference
    {
        return $this->model->create($data);
    }

    public function save(ItemPreference $itemPreference): ItemPreference
    {
        $itemPreference->save();
        return $itemPreference;
    }

    public function delete(ItemPreference $itemPreference): bool
    {
        return (bool) $itemPreference->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}