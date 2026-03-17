<?php

namespace App\Services;

use App\Models\CategoryDetail;
use App\Repositories\Contracts\CategoryDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CategoryDetailService extends BaseService
{
    public function __construct(
        private CategoryDetailRepositoryInterface $categoryDetails
    ) {}

    public function findCategoryById(int $id): array
    {
        $category = $this->categoryDetails->findById($id);

        if (!$category) {
            return $this->errorResponse(
                message: 'CategoryDetail not found.',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($category);
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
            $categories = $this->categoryDetails->paginate(
                $filters, [], $pageSize, $pageNumber
            );

            return $this->paginatedResponse($categories);
        }

        /** @var Collection $categories */
    $categories = $this->categoryDetails->getAll($filters);

        return $this->collectionResponse($categories);
    }

    public function store(array $data): array
    {
        /** @var CategoryDetail $category */
        $category = $this->categoryDetails->create($data);

        return $this->successResponse(
            data: $category,
            message: 'CategoryDetail created successfully.',
            statusCode: 201
        );
    }

    public function update(CategoryDetail $category, array $data): array
    {
        $category->fill($data);
        $this->categoryDetails->save($category);

        return $this->successResponse(
            data: $category,
            message: 'CategoryDetail updated successfully.'
        );
    }

    public function destroy(CategoryDetail $category): array
    {
        $this->categoryDetails->delete($category);

        return $this->successResponse(
            message: 'CategoryDetail deleted successfully.',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->categoryDetails->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} CategoryDetails deleted successfully.",
            statusCode: 200,
            includeData: false
        );
    }
}