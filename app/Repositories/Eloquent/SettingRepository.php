<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SettingRepository implements SettingRepositoryInterface
{
    public function __construct(
        private Setting $model
    ) {}

    public function findById($id): ?Setting
    {
        return $this->model->find($id);
    }

    public function findByKey($key): ?Setting
    {
        return $this->model->where('key', $key)->first();
    }

    public function queryWithParameters(array $filters = []): Builder
    {
        return $this->model
            ->group($filters['group'] ?? null)
            ->type($filters['type'] ?? null)
            ->search($filters['search'] ?? null)
            ->orderByField(
                $filters['sort_by'] ?? null,
                $filters['sort_direction'] ?? null
            );
    }

    public function paginateWithParameters(
        array $filters = [], 
        int $pageSize = 10, 
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithParameters($filters)
            ->paginate($pageSize, ['*'], 'page', $pageNumber);
    }

    public function getAllWithParameters(array $filters = []): Collection
    {
        return $this->queryWithParameters($filters)->get();
    }

    public function create(array $data): Setting
    {
        return $this->model->create($data);
    }

    public function save(Setting $setting): Setting
    {
        $setting->save(); // Built-in Eloquent save method
        return $setting;
    }

    public function delete(Setting $setting): bool
    {
        return $setting->delete(); // Built-in Eloquent delete method
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }

    public function getAllKeyValues(): array
    {
        return DB::table('settings')
            ->pluck('value', 'key')
            ->toArray();
    }
}