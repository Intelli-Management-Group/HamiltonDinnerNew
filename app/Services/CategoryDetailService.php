<?php

namespace App\Services;

use App\Models\CategoryDetail;
use App\Repositories\Contracts\CategoryDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CategoryDetailService
{
    public function __construct(
        private CategoryDetailRepositoryInterface $categoryDetails
    ) {}

    public function findCategoryById(int $id): array
    {
        $category = $this->categoryDetails->findById($id);

        if (!$category) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'CategoryDetail not found.',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $category
            ]
        ];
    }

    public function list(array $params): array
    {
        $filters = [
            'type' => $params['type'] ?? null,
        ];

        $usePagination = isset($params['pagesize']) || isset($params['pagenumber']);

        if ($usePagination) {
            $pageSize = (int)($params['pagesize'] ?? 15);
            $pageNumber = (int)($params['pagenumber'] ?? 1);

            /** @var LengthAwarePaginator $categories */
            $categories = $this->categoryDetails->paginateWithType(
                $filters, $pageSize, $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'data'    => $categories->items(),
                    'pagination' => [
                        'total' => $categories->total(),
                        'per_page' => $categories->perPage(),
                        'current_page' => $categories->currentPage(),
                        'last_page' => $categories->lastPage(),
                        'from' => $categories->firstItem(),
                        'to' => $categories->lastItem()
                    ]
                ]
            ];
        }

        /** @var Collection $categories */
        $categories = $this->categoryDetails->getAllWithType($filters);
        
        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $categories,
                'count'   => $categories->count()
            ]
        ];
    }

    public function store(array $data): array
    {
        /** @var CategoryDetail $category */
        $category = $this->categoryDetails->create($data);

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'message' => 'CategoryDetail created successfully.',
                'data'    => $category
            ]
        ];
    }

    public function update(CategoryDetail $category, array $data): array
    {
        $category->fill($data);
        $this->categoryDetails->save($category);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'CategoryDetail updated successfully.',
                'data'    => $category
            ]
        ];
    }

    public function destroy(CategoryDetail $category): array
    {
        $this->categoryDetails->delete($category);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'CategoryDetail deleted successfully.'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->categoryDetails->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "{$deletedCount} CategoryDetails deleted successfully."
            ]
        ];
    }
}