<?php

namespace App\Services;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SettingService extends BaseService
{
    public function __construct(
        private SettingRepositoryInterface $settings
    ) {}

    public function findSettingById(int $id): array
    {
        $setting = $this->settings->findById($id);

        if (!$setting) {
            return $this->errorResponse(
                message: 'Setting not found',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse(
            data: $setting,
            message: 'Setting retrieved successfully'
        );
    }

    public function findSettingByKey(string $key): array
    {
        $setting = $this->settings->findByKey($key);

        if (!$setting) {
            return $this->errorResponse(
                message: 'Setting not found',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse(
            data: $setting,
            message: 'Setting retrieved successfully'
        );
    }

    public function list(array $params): array
    {
        $filters = [
            'group' => $params['group'] ?? null,
            'type'  => $params['type'] ?? null,
            'search'=> $params['search'] ?? null,
            'sort_by' => $params['sort_by'] ?? null,
            'sort_direction' => $params['sort_direction'] ?? null,
        ];

        $usePagination = isset($params['pagesize']) 
                        || isset($params['pagenumber'])
                        || isset($params['page'])
                        || isset($params['per_page']);

        if ($usePagination) {
            // In other services we use 'pagesize' and 'pagenumber' for pagination.
            // Here we support both 'pagesize'/'pagenumber' and 'per_page'/'page' for flexibility.
            $pageSize = (int)($params['pagesize'] ?? $params['per_page'] ?? 10);
            $pageNumber = (int)($params['pagenumber'] ?? $params['page'] ?? 1);

            /** @var LengthAwarePaginator $settings */
            $settings = $this->settings->paginate(
                $filters,
                [],
                $pageSize,
                $pageNumber
            );

            return $this->paginatedResponse(
                paginator: $settings,
                statusCode: 200,
                message: 'Settings retrieved successfully',
                paginationKey: 'meta',
                paginationData: [
                    'current_page' => $settings->currentPage(),
                    'last_page' => $settings->lastPage(),
                    'per_page' => $settings->perPage(),
                    'total' => $settings->total(),
                ]
            );
        } else {
            /** @var Collection $settings */
            $settings = $this->settings->getAll($filters);

            return $this->collectionResponse(
                collection: $settings,
                statusCode: 200,
                message: 'Settings retrieved successfully',
                includeCount: false
            );
        }
    }

    /** 
     * Stores one or more settings.
     * We use the isBatch flag to determine if we are storing a single setting or multiple settings.
     * 
     * @param array $data
     * @return array
     */
    public function store(array $data): array
    {
        // Determine if we are dealing with a batch of settings
        $isBatch = array_is_list($data) && isset($data[0]);

        // Wrap single item in an array for uniform processing
        $items = $isBatch ? $data : [$data];

        // Create settings
        $createdSettings = [];
        foreach ($items as $item) {
            $createdSettings[] = $this->settings->create($item);
        }

        return $this->successResponse(
            data: $isBatch ? $createdSettings : $createdSettings[0],
            message: count($createdSettings) . ' setting(s) created successfully',
            statusCode: 201
        );
    }

    /**
     * Updates a single setting.
     * 
     * @param Setting $setting
     * @param array $data
     * @return array
     */
    public function update(Setting $setting, array $data): array
    {
        $setting->fill($data);
        $this->settings->save($setting);

        return $this->successResponse(
            data: $setting,
            message: 'Setting updated successfully'
        );
    }

    /**
     * Checks for existing settings by key and updates them, otherwise creates new settings.
     * Accepts an array of settings.
     * Returns a 201 status code if new settings were created, otherwise 200.
     * 
     * @param array $data
     * @return array
     */
    public function bulkUpsertByKey(array $data): array
    {
        $existing = [];
        $new = [];

        $createdSettings = [];
        $updatedSettings = [];

        // Allocate items to existing or new arrays
        foreach ($data as $item) {
            $setting = $this->settings->findByKey($item['key'] ?? '');
            if ($setting) {
                $existing[] = $setting;
            } else {
                $new[] = $item;
            }
        }

        // Wrap in transaction to ensure atomicity
        DB::transaction(function () use (&$existing, &$new, &$createdSettings, &$updatedSettings) {
            foreach ($existing as $item) {
                $item->fill($item);
                $this->settings->save($item);
                $updatedSettings[] = $item;
            }

            foreach ($new as $item) {
                $createdSettings[] = $this->settings->create($item);
            }
        });

        $statusCode = count($createdSettings) > 0 ? 201 : 200;
        $message = trim((count($createdSettings) > 0 ? count($createdSettings) . " setting(s) created. " : '') .
                        (count($updatedSettings) > 0 ? count($updatedSettings) . " setting(s) updated." : ''));
        
        $settings = array_merge($createdSettings, $updatedSettings);

        return $this->successResponse(
            data: $settings,
            message: $message,
            statusCode: $statusCode
        );
    }

    /**
     * Deletes the specified setting.
     * 
     * @param Setting $setting
     * @return array
     */
    public function destroy(Setting $setting): array
    {
        $this->settings->delete($setting);

        return $this->successResponse(
            message: 'Setting deleted successfully.',
            statusCode: 200,
            includeData: false
        );
    }

    /**
     * Deletes multiple settings at once by ids.
     * 
     * @param array $ids
     * @return array
     */
    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->settings->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} setting(s) deleted successfully.",
            statusCode: 200,
            includeData: false
        );
    }
}