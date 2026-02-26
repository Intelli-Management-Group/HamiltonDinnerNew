<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function findById(
        int $id,
        array $filters = [],
        array $relations = []
    ): ?Model
    {
        return $this->query($filters, $relations)->find($id);
    }

    public function query(
        array $filters = [],
        array $relations = []
    ): Builder
    {
        $query = $this->model->newQuery();

        $query = $this->applyRelations($query, $relations);
        $query = $this->applyFilters($query, $filters);
        $query = $this->applyDeletedAtFilter($query, $filters);
        $query = $this->applyOrdering($query, $filters);

        return $query;
    }

    public function paginate(
        array $filters = [],
        array $relations = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->query($filters, $relations)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAll(
        array $filters = [],
        array $relations = [],
        array $columns = ['*']
    ): Collection
    {
        return $this->query($filters, $relations)->get($columns);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): Model
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(Model $model): Model
    {
        $model->save();
        return $model;
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }

    protected function applyRelations(
        Builder $query,
        array $relations
    ): Builder
    {
        // Only eager-load relations that are explicitly whitelisted in ALLOWED_RELATIONS.
        // This prevents callers from loading arbitrary relations (e.g. deeply nested or
        // sensitive ones) just by passing a name.
        $safeRelations = array_values(
            array_intersect($relations, $this->allowedRelations())
        );

        if (!empty($safeRelations)) {
            $query->with($safeRelations);
        }

        return $query;
    }

    protected function allowedRelations(): array
    {
        // Concrete repositories declare: protected const ALLOWED_RELATIONS = ['items', ...];
        // An empty array means no eager loading is permitted for that repository.
        return defined('static::ALLOWED_RELATIONS')
            ? static::ALLOWED_RELATIONS
            : [];
    }

    protected function allowedOrderByColumns(): array
    {
        // If ALLOWED_ORDER_BY_COLUMNS is defined and non-empty, only those columns can be
        // sorted. If the array is empty (or the constant is not declared), any alphanumeric
        // column name is accepted. Populate this whenever ordering is exposed to user input.
        return defined('static::ALLOWED_ORDER_BY_COLUMNS')
            ? static::ALLOWED_ORDER_BY_COLUMNS
            : [];
    }

    protected function applyOrdering(
        Builder $query,
        array $filters
    ): Builder
    {
        if (empty($filters['order_by'])) {
            return $query;
        }

        $columns = is_array($filters['order_by'])
            ? $filters['order_by']
            : [$filters['order_by']];

        $directions = $filters['order_direction'] ?? 'asc';
        $directionList = is_array($directions)
            ? $directions
            : array_fill(0, count($columns), $directions);

        foreach ($columns as $index => $column) {
            if (!$this->isAllowedOrderByColumn($column)) {
                continue;
            }

            $direction = $directionList[$index] ?? 'asc';
            $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';

            $query->orderBy($column, $direction);
        }

        return $query;
    }

    protected function isAllowedOrderByColumn(?string $column): bool
    {
        if (!$column) {
            return false;
        }

        if (!preg_match('/^[A-Za-z0-9_.]+$/', $column)) {
            return false;
        }

        $allowed = $this->allowedOrderByColumns();
        if (empty($allowed)) {
            return true;
        }

        return in_array($column, $allowed, true);
    }

    protected function applyDeletedAtFilter(
        Builder $query,
        array $filters
    ): Builder
    {
        // Only active when the caller explicitly passes a 'deleted_at' key.
        // Without it, the model's default scope applies (soft-delete models exclude deleted rows).
        //
        // Accepted values:
        //   'only'    / 'onlyTrashed' → only soft-deleted rows
        //   'with'    / 'withTrashed' → all rows, including deleted
        //   'without' / 'exclude' / null → explicitly exclude deleted (same as default)
        if (!array_key_exists('deleted_at', $filters)) {
            return $query;
        }

        if (!in_array(SoftDeletes::class, class_uses_recursive($this->model), true)) {
            return $query;
        }

        $value = $filters['deleted_at'];
        $deletedAtColumn = $this->model->getDeletedAtColumn();

        if ($value === null || $value === 'exclude' || $value === 'without') {
            $query->whereNull($deletedAtColumn);
        } elseif ($value === 'only') {
            $query->onlyTrashed();
        } elseif ($value === 'with') {
            $query->withTrashed();
        }

        return $query;
    }

    // Every concrete repository must implement this to add model-specific WHERE clauses,
    // default ordering, or joins. It is called automatically by query() / paginate() / getAll().
    // The $filters array is whatever the caller passed — check existing repositories for examples.
    abstract protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder;
}
