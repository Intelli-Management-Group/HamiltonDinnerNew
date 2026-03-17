<?php

namespace App\Repositories\Eloquent;

use App\Models\MenuDetail;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class MenuDetailRepository extends BaseRepository implements MenuDetailRepositoryInterface
{
    protected const ALLOWED_RELATIONS = [];

    public function __construct(
        MenuDetail $model
    ) {
        parent::__construct($model);
    }

    /** 
     * Find MenuDetail by date.
     * 
     * @param string $date
     * @return MenuDetail|null
     */
    public function findByDate(string $date): ?MenuDetail
    {
        return $this->model
            ->whereDate('date', $date) // whereDate is from Eloquent's query builder
            ->first();
    }

    /** Find most recent MenuDetail.
     * 
     * @return MenuDetail|null
     */
    public function findLatest(): ?MenuDetail
    {
        return $this->model
            ->orderBy('date', 'desc')
            ->first();
    }

    /** 
     * Find most recent MenuDetail date.
     * 
     * @return string|null
     */
    public function findLatestDate(): ?string
    {
        $latest = $this->findLatest();
        return $latest?->date?->toJSON();
    }

    public function findSoftDeletedByDate(string $date): ?MenuDetail
    {
        return $this->model->withTrashed()
            ->whereDate('date', $date)
            ->first();
    }


    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (!empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        return $query->latest();
    }
}