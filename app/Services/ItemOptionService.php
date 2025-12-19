<?php

namespace App\Services;

use App\Models\ItemOption;
use App\Repositories\Contracts\ItemOptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemOptionService
{
    public function __construct(
        private ItemOptionRepositoryInterface $itemOptions
    ) {}

    public function findItemOptionById(int $id): ?ItemOption
    {
        $option = $this->itemOptions->findById($id);

        if (!$option) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'ItemOption not found.',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $option
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
            $options = $this->itemOptions->paginateWithCategoryId(
                $filters, $pageSize, $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'data'    => $options->items(),
                    'pagination' => [
                        'total' => $options->total(),
                        'per_page' => $options->perPage(),
                        'current_page' => $options->currentPage(),
                        'last_page' => $options->lastPage(),
                        'from' => $options->firstItem(),
                        'to' => $options->lastItem(),
                    ]
                ]
            ];
        }

        /** @var Collection $options */
        $options = $this->itemOptions->getAllWithCategoryId($filters);
        
        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $options,
                'count'   => $options->count()
            ]
        ];
    }

    public function store(array $data): array
    {
        $option = $this->itemOptions->create($data);

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'message' => 'ItemOption created successfully.',
                'data'    => $option
            ]
        ];
    }

    public function update(ItemOption $option, array $data): array
    {
        $option->fill($data);
        $this->itemOptions->save($option);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'ItemOption updated successfully.',
                'data'    => $option
            ]
        ];
    }

    public function destroy(ItemOption $option): array
    {
        $this->itemOptions->delete($option);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'ItemOption deleted successfully.'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->itemOptions->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "{$deletedCount} ItemOptions deleted successfully."
            ]
        ];
    }
}