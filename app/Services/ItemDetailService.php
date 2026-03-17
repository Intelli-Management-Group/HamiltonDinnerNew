<?php

namespace App\Services;

use App\Models\ItemDetail;
use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemDetailService extends BaseService
{
    public function __construct(
        private ItemDetailRepositoryInterface $itemDetails
    ) {}

    public function findItemById(int $id): array
    {
        $item = $this->itemDetails->findById($id);

        if (!$item) {
            return $this->errorResponse(
                message: 'ItemDetail not found.',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($item);
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
            $items = $this->itemDetails->paginate(
                $filters, [], $pageSize, $pageNumber
            );

            return $this->paginatedResponse($items);
        }

        /** @var Collection $items */
    $items = $this->itemDetails->getAll($filters);

        return $this->collectionResponse($items);
    }

    public function store(array $data): array
    {
        /** @var ItemDetail $item */
        $item = $this->itemDetails->create($data);

        return $this->successResponse(
            data: $item,
            message: 'ItemDetail created successfully.',
            statusCode: 201
        );
    }

    public function update(ItemDetail $item, array $data): array
    {
        $item->fill($data);
        $this->itemDetails->save($item);

        return $this->successResponse(
            data: $item,
            message: 'ItemDetail updated successfully.'
        );
    }

    public function destroy(ItemDetail $item): array
    {
        $this->itemDetails->delete($item);

        return $this->successResponse(
            message: 'ItemDetail deleted successfully.',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->itemDetails->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} ItemDetails deleted successfully.",
            statusCode: 200,
            includeData: false
        );
    }

    public function getItemsByCategory(int $categoryId): array
    {
        $items = $this->itemDetails->findByCategoryWithParentId($categoryId);

        $results = $items->map(fn ($item) => [
            'type'         => empty($item->parent_id) ? 'item' : 'sub_cat_item',
            'parent_id'    => $item->parent_id,
            'item_name'    => $item->item_name,
            'item_id'      => $item->id,
            'preference'   => $item->preference,
            'options'      => $item->options,
            'chinese_name' => $item->item_chinese_name,
            'item_image'   => $item->image,
            'comment'      => '',
            'qty'          => 0,
            'order_id'     => 0,
        ])->values()->all();

        return ['Data' => $results];
    }
}