<?php

namespace App\Repositories\Contracts;

use App\Models\FormType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface FormTypeRepositoryInterface
{
    public function findById($id): ?FormType;

    public function query(array $filters = []): Builder;

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(array $filters = []): Collection;

    public function create(array $data): FormType;

    public function save(FormType $formType): FormType;

    public function delete(FormType $formType): bool;

    public function bulkDeleteByIds(array $ids): int;
}