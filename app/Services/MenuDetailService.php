<?php

namespace App\Services;

use App\Models\MenuDetail;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MenuDetailService
{
    public function __construct(
        private MenuDetailRepositoryInterface $menuDetails
    ) {}

    public function findMenuById(int $id): array
    {
        $menu = $this->menuDetails->findById($id);

        if (!$menu) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'MenuDetail not found.',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $menu
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

            /** @var LengthAwarePaginator $menus */
            $menus = $this->menuDetails->paginate(
                $filters, $pageSize, $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'data'    => $menus->items(),
                    'pagination' => [
                        'total' => $menus->total(),
                        'per_page' => $menus->perPage(),
                        'current_page' => $menus->currentPage(),
                        'last_page' => $menus->lastPage(),
                        'from' => $menus->firstItem(),
                        'to' => $menus->lastItem(),
                    ]
                ]
            ];
        }

        /** @var Collection $menus */
        $menus = $this->menuDetails->getAll($filters);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $menus,
                'count'   => $menus->count()
            ]
        ];
    }

    public function store(array $data): array
    {
        /** @var MenuDetail|null $existing */
        $existing = $this->menuDetails->findSoftDeletedByDate($data['date']);

        if ($existing && $existing->trashed()) {
            $existing->restore();

            $existing->items = $data['items'] ?? $existing->items;
            $existing->is_allday = $data['is_allday'] ?? $existing->is_allday;
            $this->menuDetails->save($existing);

            $existing->refresh();

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'message' => 'MenuDetail restored and updated successfully.',
                    'data'    => $existing
                ]
            ];
        }

        /** @var MenuDetail $menu */
        $menu = $this->menuDetails->create($data);

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'message' => 'MenuDetail created successfully.',
                'data'    => $menu
            ]
        ];
    }

    public function update(MenuDetail $menu, array $data): array
    {
        $menu->fill($data);
        $this->menuDetails->save($menu);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'MenuDetail updated successfully.',
                'data'    => $menu
            ]
        ];
    }

    public function destroy(MenuDetail $menu): array
    {
        $this->menuDetails->delete($menu);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'MenuDetail deleted successfully.'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->menuDetails->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "{$deletedCount} MenuDetails deleted successfully."
            ]
        ];
    }
}