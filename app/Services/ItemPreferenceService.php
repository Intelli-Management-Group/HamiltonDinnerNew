<?php

namespace App\Services;

use App\Models\ItemPreference;
use App\Repositories\Contracts\ItemPreferenceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ItemPreferenceService
{
    public function __construct(
        private ItemPreferenceRepositoryInterface $itemPreferences
    ) {}

    public function findItemById(int $id): ?ItemPreference
    {
        $preference = $this->itemPreferences->findById($id);

        if (!$preference) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'ItemPreference not found.',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $preference
            ]
        ];
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
                $filters, $pageSize, $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'data'    => $preferences->items(),
                    'pagination' => [
                        'total' => $preferences->total(),
                        'per_page' => $preferences->perPage(),
                        'current_page' => $preferences->currentPage(),
                        'last_page' => $preferences->lastPage(),
                        'from' => $preferences->firstItem(),
                        'to' => $preferences->lastItem(),
                    ]
                ]
            ];
        }

        /** @var Collection $preferences */
        $preferences = $this->itemPreferences->getAll($filters);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $preferences,
                'count'   => $preferences->count()
            ]
        ];
    }

    public function store(array $data): array
    {
        /** @var ItemPreference $preference */
        $preference = $this->itemPreferences->create($data);

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'message' => 'ItemPreference created successfully.',
                'data'    => $preference
            ]
        ];
    }

    public function update(ItemPreference $preference, array $data): array
    {
        $preference->fill($data);
        $this->itemPreferences->save($preference);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'ItemPreference updated successfully.',
                'data'    => $preference
            ]
        ];
    }

    public function destroy(ItemPreference $preference): array
    {
        $this->itemPreferences->delete($preference);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'ItemPreference deleted successfully.'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->itemPreferences->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "{$deletedCount} ItemPreferences deleted successfully."
            ]
        ];
    }
}