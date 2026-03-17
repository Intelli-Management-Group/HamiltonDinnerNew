<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\MoveInSummaryValues;
use App\Repositories\Contracts\Forms\MoveInSummaryValuesRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class MoveInSummaryValuesRepository extends BaseRepository implements MoveInSummaryValuesRepositoryInterface
{
    public function __construct(
        MoveInSummaryValues $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        // move_in_summary_values has no created_at; order by key_param instead
        return $query->orderBy('key_param');
    }
}