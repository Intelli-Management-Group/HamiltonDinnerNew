<?php

namespace App\Repositories\Contracts;

use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface SettingRepositoryInterface
{
    public function findById($id): ?Setting;

    public function findByKey($key): ?Setting;

    public function queryWithParameters(array $filters = []): Builder;

    public function paginateWithParameters(
        array $filters = [],
        int $perPage = 15,
        int $page = 1
    ): LengthAwarePaginator;

    public function getAllWithParameters(array $filters = []): Collection;

    public function create(array $data): Setting;

    public function save(Setting $setting): Setting;

    public function delete(Setting $setting): bool;

    public function bulkDeleteByIds(array $ids): int;

    public function getAllKeyValues(): array;
}