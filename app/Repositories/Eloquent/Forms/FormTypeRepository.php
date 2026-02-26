<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\FormType;
use App\Repositories\Contracts\Forms\FormTypeRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class FormTypeRepository extends BaseRepository implements FormTypeRepositoryInterface
{
    public function __construct(
        FormType $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        return $query->orderByDesc('id');
    }
}