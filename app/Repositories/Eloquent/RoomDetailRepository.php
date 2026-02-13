<?php

namespace App\Repositories\Eloquent;

use App\Models\RoomDetail;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoomDetailRepository implements RoomDetailRepositoryInterface
{
    // Allowed relations and scopes for eager loading
    private const ALLOWED_RELATIONS = [];

    public function __construct(
        private RoomDetail $model
    ) {}

    public function findById(
        int $id,
        array $filters = [],
        array $relations = []
    ): ?RoomDetail
    {
        return $this->query($filters, $relations)->find($id);
    }

    public function query(
        array $filters = [],
        array $relations = []
    ): Builder
    {
        $query = $this->model->newQuery();

        // Eager load allowed relations
        $query = $this->applyRelations($query, $relations);

        if (array_key_exists('room_name', $filters)) {
            $query->where('room_name', 'like', '%' . $filters['room_name'] . '%');
        }

        if (array_key_exists('password', $filters)) {
            $query->where('password', $filters['password']);
        }

        // Apply allowed scopes
        // isActive() is a local scope defined in the RoomDetail model
        // for filtering based on active status.
        // Please refer to scopeIsActive() in RoomDetail model for details.
        return $query
            ->isActive($filters['is_active'] ?? null)
            ->latest();
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

    public function create(array $data): RoomDetail
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): RoomDetail
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(RoomDetail $room): RoomDetail
    {
        $room->save();
        return $room;
    }

    public function delete(RoomDetail $room): bool
    {
        return (bool) $room->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }

    // Helper functions to apply filters, relations, and scopes
    private function applyRelations(
        Builder $query,
        array $relations
    ): Builder
    {
        $safe_relations = array_values(
            array_intersect($relations, self::ALLOWED_RELATIONS)
        );

        if (!empty($safe_relations)) {
            $query->with($safe_relations);
        }

        return $query;
    }
}