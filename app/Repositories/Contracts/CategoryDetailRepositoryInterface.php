<?php

namespace App\Repositories\Contracts;

interface CategoryDetailRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Resolve which DB category IDs correspond to each report role.
     *
     * Returns:
     *   catId         — [id => 'BA'|'BB']  (Western/Chinese Breakfast)
     *   alternative   — [id, ...]           (Lunch/Dinner Alternatives — numeric suffix)
     *   abAlternative — [id, ...]           (Lunch/Dinner Entrées — lettered suffix)
     *   excluded      — [id, ...]           (categories excluded from print/report output)
     */
    public function getCategoryRoleMappings(): array;
}