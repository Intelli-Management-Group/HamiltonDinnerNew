<?php

namespace App\Services;

use App\Models\ItemOption;
use App\Repositories\Contracts\ItemOptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemOptionService extends BaseService
{
    public function __construct(
        private ItemOptionRepositoryInterface $itemOptions
    ) {}

    public function findItemOptionById(int $id): array
    {
        $option = $this->itemOptions->findById($id);

        if (!$option) {
            return $this->errorResponse(
                message: 'ItemOption not found.',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($option);
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
            $options = $this->itemOptions->paginate(
                $filters, [], $pageSize, $pageNumber
            );

            return $this->paginatedResponse($options);
        }

        /** @var Collection $options */
    $options = $this->itemOptions->getAll($filters);

        return $this->collectionResponse($options);
    }

    public function store(array $data): array
    {
        $option = $this->itemOptions->create($data);

        return $this->successResponse(
            data: $option,
            message: 'ItemOption created successfully.',
            statusCode: 201
        );
    }

    public function update(ItemOption $option, array $data): array
    {
        $option->fill($data);
        $this->itemOptions->save($option);

        return $this->successResponse(
            data: $option,
            message: 'ItemOption updated successfully.'
        );
    }

    public function destroy(ItemOption $option): array
    {
        $this->itemOptions->delete($option);

        return $this->successResponse(
            message: 'ItemOption deleted successfully.',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->itemOptions->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} ItemOptions deleted successfully.",
            statusCode: 200,
            includeData: false
        );
    }
}