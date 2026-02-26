<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\FormResponse;
use App\Repositories\Contracts\Forms\FormResponseRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FormResponseRepository extends BaseRepository implements FormResponseRepositoryInterface
{
    public function __construct(
        FormResponse $model
    ) {
        parent::__construct($model);
    }

    public function queryWithFormType(array $filters = []): Builder
    {
    return $this->query($filters, []);
    }

    public function paginateWithFormType(
        array $filters = [],
        int $perPage = 10,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithFormType($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAllWithFormType(array $filters = [], array $columns = ['*']): Collection
    {
        return $this->queryWithFormType($filters)->get($columns);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        return $query
            ->withFormType()
            ->orderBy('created_at', 'desc')
            ->latest();
    }
}