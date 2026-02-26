<?php

namespace App\Repositories\Eloquent;

use App\Models\ItemDetail;
use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemDetailRepository extends BaseRepository implements ItemDetailRepositoryInterface
{
    // Allowed relations and scopes for eager loading
    protected const ALLOWED_RELATIONS = [
        'category',
        'options',
        'preference',
    ];

    public function __construct(
        ItemDetail $model
    ) {
        parent::__construct($model);
    }

    public function findById(
        int $id,
        array $filters = [],
        array $relations = []
    ): ?ItemDetail
    {
        return parent::findById($id, $filters, $relations);
    }

    public function findOrderReportSummaries(
        array $ids, 
        array $columns = ['id', 'item_name', 'cat_id']
    ): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return $this->model
            ->select($columns)
            ->whereIn('id', $ids)
            ->orderBy('cat_id')
            ->get();
    }

    public function findByIdsAndParentFlag(
        array $ids,
        bool $isParent
    ): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return $this->getAll(
            filters: [
                'id' => $ids,
                'is_parent' => $isParent,
                'deleted_at' => null,
                'order_by' => ['cat_id', 'id'],
                'order_direction' => ['asc', 'asc'],
            ],
            relations: ['category'],
            columns: [
                'id',
                'cat_id',
                'item_name',
                'item_image',
                'item_chinese_name',
                'options',
                'preference',
            ]
        );
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (array_key_exists('cat_id', $filters) 
            && $filters['cat_id'] !== null 
            && $filters['cat_id'] !== ''
        ) {
            $query->where('cat_id', $filters['cat_id']);
        }

        if (array_key_exists('id', $filters)) {
            if (is_array($filters['id'])) {
                $query->whereIn('id', $filters['id']);
            } else {
                $query->where('id', $filters['id']);
            }
        }

        if (array_key_exists('category_parent_id', $filters)
            && $filters['category_parent_id'] !== null
            && $filters['category_parent_id'] !== ''
        ) {
            $query->whereHas('category', function (Builder $categoryQuery) use ($filters) {
                $categoryQuery->where('parent_id', $filters['category_parent_id']);
            });
        }

        if (array_key_exists('is_parent', $filters)
            && $filters['is_parent'] !== null
            && $filters['is_parent'] !== ''
        ) {
            $isParent = (bool) $filters['is_parent'];
            $operator = $isParent ? '=' : '!=';

            $query->whereHas('category', function (Builder $categoryQuery) use ($operator) {
                $categoryQuery->where('parent_id', $operator, 0);
            });
        }

        return $query->latest();
    }
}