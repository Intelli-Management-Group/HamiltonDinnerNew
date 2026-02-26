<?php

namespace App\Repositories\Contracts\Forms;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface FormResponseRepositoryInterface extends BaseRepositoryInterface
{
    public function queryWithFormType(array $filters = []): Builder;

    public function paginateWithFormType(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAllWithFormType(array $filters = []): Collection;
}