<?php

namespace App\Services;

use App\Models\ItemDetail;
use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemDetailService
{
    public function __construct(
        private ItemDetailRepositoryInterface $itemDetails
    ) {}

    public function findItemById(int $id): ?ItemDetail
    {
        $item = $this->itemDetails->findById($id);

        if (!$item) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'ItemDetail not found.',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $item
            ]
        ];
    }

    public function list(array $params): array
    {
        $filters = [
            'cat_id' => $params['cat_id'] ?? null,
        ];

        $usePagination = isset($params['pagesize']) || isset($params['pagenumber']);

        if ($usePagination) {
            $pageSize = (int)($params['pagesize'] ?? 15);
            $pageNumber = (int)($params['pagenumber'] ?? 1);

            /** @var LengthAwarePaginator $items */
            $items = $this->itemDetails->paginateWithCategoryId(
                $filters, $pageSize, $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'data'    => $items->items(),
                    'pagination' => [
                        'total' => $items->total(),
                        'per_page' => $items->perPage(),
                        'current_page' => $items->currentPage(),
                        'last_page' => $items->lastPage(),
                        'from' => $items->firstItem(),
                        'to' => $items->lastItem()
                    ]
                ]
            ];
        }

        /** @var Collection $items */
        $items = $this->itemDetails->getAllWithCategoryId($filters);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $items,
                'count'   => $items->count()
            ]
        ];
    }

    public function store(array $data): array
    {
        /** @var ItemDetail $item */
        $item = $this->itemDetails->create($data);

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'message' => 'ItemDetail created successfully.',
                'data'    => $item
            ]
        ];
    }

    public function update(ItemDetail $item, array $data): array
    {
        $item->fill($data);
        $this->itemDetails->save($item);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'ItemDetail updated successfully.',
                'data'    => $item
            ]
        ];
    }

    public function destroy(ItemDetail $item): array
    {
        $this->itemDetails->delete($item);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'ItemDetail deleted successfully.'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->itemDetails->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "{$deletedCount} ItemDetails deleted successfully."
            ]
        ];
    }
}