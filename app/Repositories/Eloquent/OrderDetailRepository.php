<?php

namespace App\Repositories\Eloquent;

use App\Models\OrderDetail;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrderDetailRepository implements OrderDetailRepositoryInterface
{
    // Allowed relations and scopes for eager loading
    private const ALLOWED_RELATIONS = [];

    public function __construct(
        private OrderDetail $model
    ) {}

    public function findById(
        $id,
        array $filters = [],
        array $relations = [],
    ): ?OrderDetail
    {
        return $this->query($filters, $relations)->find($id);
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

    public function query(
        array $filters = [],
        array $relations = []
    ): Builder
    {
        $query = $this->model->newQuery();

        // Eager load allowed relations
        $query = $this->applyRelations($query, $relations);

        // Handle filtering
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

        if (isset($filters['order_by'])) {
            $query->orderBy($filters['order_by'], $filters['order_direction'] ?? 'asc');
        }

        return $query->latest();
    }

    public function paginate(
        array $filters = [],
        array $relations = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator {
        return $this->query($filters, $relations)
            ->paginate($perPage, ['*'], 'page', $pageNumber);
    }

    public function getAll(
        array $filters = [],
        array $relations = [],
        array $columns = ['*']
    ): Collection
    {
        return $this->query($filters, $relations)->get($columns);
    }

    public function updateByFilters(array $filters, array $data): int
    {
        return $this->query($filters)->update($data);
    }

    public function create(array $data): OrderDetail
    {
        return $this->model->create($data);
    }

    public function upsertByFilters(array $filters, array $data): OrderDetail
    {
        return $this->model->updateOrCreate($filters, $data);
    }

    public function save(OrderDetail $orderDetail): OrderDetail
    {
        $orderDetail->save();

        return $orderDetail;
    }

    public function delete(OrderDetail $orderDetail): bool
    {
        return $orderDetail->delete();
    }

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->delete();
    }

    // Helper functions to apply filters, relations, and scopes
    private function applyRelations(
        Builder $query,
        array $relations
    ): Builder
    {
        $safe_relations = array_values(
            array_intersect($relations, self::ALLOWED_RELATIONS)
        );

        if (!empty($safe_relations)) {
            $query->with($safe_relations);
        }

        return $query;
    }
}