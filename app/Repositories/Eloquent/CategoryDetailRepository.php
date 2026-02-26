<?php

namespace App\Repositories\Eloquent;

use App\Models\CategoryDetail;
use App\Repositories\Contracts\CategoryDetailRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CategoryDetailRepository extends BaseRepository implements CategoryDetailRepositoryInterface
{
    // Allowed relations and scopes for eager loading
    protected const ALLOWED_RELATIONS = [
        'items',
        'catParentId',
        'parentId',
    ];

    public function __construct(
        CategoryDetail $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (array_key_exists('parent_id', $filters)) {
            $query->where('parent_id', $filters['parent_id']);
        }

        if (array_key_exists('type', $filters) && $filters['type'] !== null && $filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        return $query->latest();
    }
}