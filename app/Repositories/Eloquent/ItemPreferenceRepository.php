<?php

namespace App\Repositories\Eloquent;

use App\Models\ItemPreference;
use App\Repositories\Contracts\ItemPreferenceRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ItemPreferenceRepository extends BaseRepository implements ItemPreferenceRepositoryInterface
{
    public function __construct(
        ItemPreference $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        return $query->latest();
    }
}