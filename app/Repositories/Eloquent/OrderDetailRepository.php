<?php

namespace App\Repositories\Eloquent;

use App\Models\OrderDetail;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrderDetailRepository extends BaseRepository implements OrderDetailRepositoryInterface
{
    // Allowed relations and scopes for eager loading
    protected const ALLOWED_RELATIONS = [];

    public function __construct(
        OrderDetail $model
    ) {
        parent::__construct($model);
    }

    /**
     * Find order report summaries by date and an array of IDs.
     *
     * @param array $ids
     * @return Collection
     */
    public function findOrderReportSummaries(string $date, array $ids): Collection
    {
        return $this->query(
            filters: ['date' => $date, 'item_id' => $ids],
            relations: []
        )->get([
            'room_id',
            'item_id',
            'quantity',
            'is_for_guest'
        ]);
    }

    public function sumQuantityByDateAndItem(string $date, int $itemId): int
    {
        return (int) $this->query(
            filters: ['date' => $date, 'item_id' => $itemId]
        )->sum('quantity');
    }

    public function updateByFilters(array $filters, array $data): int
    {
        return $this->query($filters)->update($data);
    }

    public function upsertByFilters(array $filters, array $data): OrderDetail
    {
        $this->query($filters)->update($data);

        return $this->query($filters)->first()
            ?? $this->model->newInstance(array_merge($filters, $data));
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (isset($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['item_id'])) {
            if (is_array($filters['item_id'])) {
                $query->whereIn('item_id', $filters['item_id']);
            } else {
                $query->where('item_id', $filters['item_id']);
            }
        }

        if (isset($filters['is_for_guest'])) {
            $query->where('is_for_guest', (int) $filters['is_for_guest']);
        }

        if (isset($filters['item_options'])) {
            $query->where('item_options', $filters['item_options']);
        }

        if (isset($filters['order_by'])) {
            $query->orderBy($filters['order_by'], $filters['order_direction'] ?? 'asc');
        }

        return $query->latest();
    }
}