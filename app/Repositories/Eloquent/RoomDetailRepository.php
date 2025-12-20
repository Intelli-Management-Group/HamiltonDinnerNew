<?php

namespace App\Repositories\Eloquent;

use App\Models\RoomDetail;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoomDetailRepository implements RoomDetailRepositoryInterface
{
    public function __construct(
        private RoomDetail $model
    ) {}

    public function findById($id): ?RoomDetail
    {
        return $this->model->find($id);
    }

    public function queryWithActiveStatus(array $filters = []): Builder
    {
        return $this->model
            ->isActive($filters['is_active'] ?? null)
            ->latest();
    }

    public function paginateWithActiveStatus(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithActiveStatus($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAllWithActiveStatus(array $filters = []): Collection
    {
        return $this->queryWithActiveStatus($filters)->get();
    }

    public function create(array $data): RoomDetail
    {
        return $this->model->create($data);
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
}