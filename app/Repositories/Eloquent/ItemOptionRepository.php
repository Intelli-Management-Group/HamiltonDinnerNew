<?php

namespace App\Repositories\Eloquent;

use App\Models\ItemOption;
use App\Repositories\Contracts\ItemOptionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ItemOptionRepository extends BaseRepository implements ItemOptionRepositoryInterface
{
    public function __construct(
        ItemOption $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (array_key_exists('cat_id', $filters) && $filters['cat_id'] !== null && $filters['cat_id'] !== '') {
            $query->where('cat_id', $filters['cat_id']);
        }

        return $query->latest();
    }
}