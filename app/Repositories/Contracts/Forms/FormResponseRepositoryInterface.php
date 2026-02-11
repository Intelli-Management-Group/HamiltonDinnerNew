<?php

namespace App\Repositories\Contracts\Forms;

use App\Models\FormType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
    
interface FormResponseRepositoryInterface
{
    public function findById($id): ?FormResponse;

    public function queryWithFormType(array $filters = []): Builder;

    public function paginateWithFormType(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAllWithFormType(array $filters = []): Collection;

    public function create(array $data): FormResponse;

    public function save(FormResponse $formResponse): FormResponse;
    public function delete(FormResponse $formResponse): bool;

    public function bulkDeleteByIds(array $ids): int;
}