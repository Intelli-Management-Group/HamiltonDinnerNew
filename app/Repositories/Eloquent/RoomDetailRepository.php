<?php

namespace App\Repositories\Eloquent;

use App\Models\RoomDetail;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class RoomDetailRepository extends BaseRepository implements RoomDetailRepositoryInterface
{
    // Allowed relations and scopes for eager loading
    protected const ALLOWED_RELATIONS = [];

    public function __construct(
        RoomDetail $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (array_key_exists('room_name', $filters)) {
            $query->where('room_name', 'like', '%' . $filters['room_name'] . '%');
        }

        if (array_key_exists('password', $filters)) {
            $query->where('password', $filters['password']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->latest();
    }

    public function getAllDeviceTokens(): array
    {
        return $this->model
            ->whereNotNull('device_token')
            ->where('is_active', 1)
            ->pluck('device_token')
            ->all();
    }
}