<?php

namespace App\Services;

use App\Models\ItemPreference;
use App\Repositories\Contracts\ItemPreferenceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemPreferenceService extends BaseService
{
    public function __construct(
        private ItemPreferenceRepositoryInterface $itemPreferences
    ) {}

    public function findItemById(int $id): array
    {
        $preference = $this->itemPreferences->findById($id);

        if (!$preference) {
            return $this->errorResponse(
                message: 'ItemPreference not found.',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($preference);
    }

    public function list(array $params): array
    {
        $filters = [
            // Add filter mappings if needed
        ];

        $usePagination = isset($params['pagesize']) || isset($params['pagenumber']);

        if ($usePagination) {
            $pageSize = (int)($params['pagesize'] ?? 15);
            $pageNumber = (int)($params['pagenumber'] ?? 1);

            /** @var LengthAwarePaginator $preferences */
            $preferences = $this->itemPreferences->paginate(
                $filters, [], $pageSize, $pageNumber
            );

            return $this->paginatedResponse($preferences);
        }

        /** @var Collection $preferences */
        $preferences = $this->itemPreferences->getAll($filters);

        return $this->collectionResponse($preferences);
    }

    public function store(array $data): array
    {
        /** @var ItemPreference $preference */
        $preference = $this->itemPreferences->create($data);

        return $this->successResponse(
            data: $preference,
            message: 'ItemPreference created successfully.',
            statusCode: 201
        );
    }

    public function update(ItemPreference $preference, array $data): array
    {
        $preference->fill($data);
        $this->itemPreferences->save($preference);

        return $this->successResponse(
            data: $preference,
            message: 'ItemPreference updated successfully.'
        );
    }

    public function destroy(ItemPreference $preference): array
    {
        $this->itemPreferences->delete($preference);

        return $this->successResponse(
            message: 'ItemPreference deleted successfully.',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->itemPreferences->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} ItemPreferences deleted successfully.",
            statusCode: 200,
            includeData: false
        );
    }
}