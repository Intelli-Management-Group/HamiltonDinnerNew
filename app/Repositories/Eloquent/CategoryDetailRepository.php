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

    private const BREAKFAST_ENTREES = [
        'Western Breakfast' => 'BA',
        'Chinese Breakfast' => 'BB',
    ];
    private const ALTERNATIVES        = ['Lunch Alternative', 'Dinner Alternative'];
    private const MEAL_ENTREES        = ['Western Lunch Entrée', 'Chinese Lunch Entrée',
                                         'Western Dinner Entrée', 'Chinese Dinner Entrée'];
    private const EXCLUDED_FROM_REPORTS = ['Lunch Soup', 'Lunch Dessert', 'Dinner Dessert'];

    public function getCategoryRoleMappings(): array
    {
        $allNames = array_merge(
            array_keys(self::BREAKFAST_ENTREES),
            self::ALTERNATIVES,
            self::MEAL_ENTREES,
            self::EXCLUDED_FROM_REPORTS
        );

        $cats = $this->model->whereIn('cat_name', $allNames)->get(['id', 'cat_name']);

        $catId = []; $alternative = []; $abAlternative = []; $excluded = [];
        foreach ($cats as $cat) {
            if (isset(self::BREAKFAST_ENTREES[$cat->cat_name]))            $catId[$cat->id]  = self::BREAKFAST_ENTREES[$cat->cat_name];
            if (in_array($cat->cat_name, self::ALTERNATIVES))              $alternative[]    = $cat->id;
            if (in_array($cat->cat_name, self::MEAL_ENTREES))              $abAlternative[]  = $cat->id;
            if (in_array($cat->cat_name, self::EXCLUDED_FROM_REPORTS))     $excluded[]       = $cat->id;
        }

        return compact('catId', 'alternative', 'abAlternative', 'excluded');
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