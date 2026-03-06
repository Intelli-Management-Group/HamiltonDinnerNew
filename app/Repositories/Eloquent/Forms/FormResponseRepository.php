<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\FormResponse;
use App\Repositories\Contracts\Forms\FormResponseRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class FormResponseRepository extends BaseRepository implements FormResponseRepositoryInterface
{
    protected const ALLOWED_RELATIONS = ['formType'];

    public function __construct(
        FormResponse $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (!empty($filters['form_type_id'])) {
            $query->where('form_type_id', $filters['form_type_id']);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->latest();
    }
}
