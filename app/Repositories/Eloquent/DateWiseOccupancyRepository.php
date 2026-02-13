<?php

namespace App\Repositories\Eloquent;

use App\Models\DateWiseOccupancy;
use App\Repositories\Contracts\DateWiseOccupancyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DateWiseOccupancyRepository implements DateWiseOccupancyRepositoryInterface
{
    public function __construct(
        private DateWiseOccupancy $model
    ) {}

    public function findById($id): ?DateWiseOccupancy
    {
        return $this->model->find($id);
    }

    public function query(array $filters = []): Builder
    {
        $query = $this->model->newQuery();

        if (isset($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['order_by'])) {
            $query->orderBy($filters['order_by'], $filters['order_direction'] ?? 'asc');
        }

        return $query->latest();
    }

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAll(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    public function upsertByFilters(array $filters, array $data): DateWiseOccupancy
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function create(array $data): DateWiseOccupancy
    {
        return $this->model->create($data);
    }

    public function save(DateWiseOccupancy $dateWiseOccupancy): DateWiseOccupancy
    {
        $dateWiseOccupancy->save();

        return $dateWiseOccupancy;
    }

    public function delete(DateWiseOccupancy $dateWiseOccupancy): bool
    {
        return (bool) $dateWiseOccupancy->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }
}
