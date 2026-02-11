<?php

namespace App\Repositories\Contracts\Forms;

use App\Models\MoveInSummaryValues;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface MoveInSummaryValuesRepositoryInterface
{

    public function findById($id): ?MoveInSummaryValues;

    public function query(array $filters = []): Builder;

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(array $filters = []): Collection;

    public function create(array $data): MoveInSummaryValues;
    public function save(MoveInSummaryValues $moveInSummaryValues): MoveInSummaryValues;

    public function delete(MoveInSummaryValues $moveInSummaryValues): bool;

    public function bulkDeleteByIds(array $ids): int;
}