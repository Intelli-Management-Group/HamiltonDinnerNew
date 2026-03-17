<?php

namespace App\Repositories\Eloquent;

use App\Models\DateWiseOccupancy;
use App\Repositories\Contracts\DateWiseOccupancyRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class DateWiseOccupancyRepository extends BaseRepository implements DateWiseOccupancyRepositoryInterface
{
    public function __construct(
        DateWiseOccupancy $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
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
}
