<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SettingRepository extends BaseRepository implements SettingRepositoryInterface
{
    public function __construct(
        Setting $model
    ) {
        parent::__construct($model);
    }

    public function findByKey($key): ?Setting
    {
        return $this->model->where('key', $key)->first();
    }

    public function getAllKeyValues(): array
    {
        return DB::table('settings')
            ->pluck('value', 'key')
            ->toArray();
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (array_key_exists('group', $filters) && $filters['group'] !== null && $filters['group'] !== '') {
            $query->where('group', $filters['group']);
        }

        if (array_key_exists('type', $filters) && $filters['type'] !== null && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        return $query
            ->search($filters['search'] ?? null)
            ->orderByField(
                $filters['sort_by'] ?? null,
                $filters['sort_direction'] ?? null
            );
    }
}