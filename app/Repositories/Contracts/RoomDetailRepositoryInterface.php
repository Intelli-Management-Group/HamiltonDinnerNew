<?php

namespace App\Repositories\Contracts;

use App\Models\RoomDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface RoomDetailRepositoryInterface
{
    public function findById(
        int $id,
        array $filters = [],
        array $relations = []
    ): ?RoomDetail;

    public function query(
        array $filters = [],
        array $relations = []
    ): Builder;

    public function paginate(
        array $filters = [],
        array $relations = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(
        array $filters = [],
        array $relations = [],
        array $columns = ['*']
    ): Collection;

    public function create(array $data): RoomDetail;
    public function upsertByFilters(array $filters, array $data): RoomDetail;

    public function save(RoomDetail $room): RoomDetail;

    public function delete(RoomDetail $room): bool;

    public function bulkDeleteByIds(array $ids): int;
}

