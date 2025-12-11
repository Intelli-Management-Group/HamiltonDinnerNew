<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $roles
    ) {}

    public function findRoleById(int $id): array
    {
        $role = $this->roles->findById($id);

        if (!$role) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'Role not found.',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $role
            ]
        ];
    }
    
    public function list(array $params): array
    {
        $filters = [
            'search' => $params['search'] ?? null,
        ];

        $usePagination = isset($params['pagesize']) || isset($params['pagenumber']);

        if ($usePagination) {
            $pageSize = (int)($params['pagesize'] ?? 15);
            $pageNumber = (int)($params['pagenumber'] ?? 1);

            /** @var LengthAwarePaginator $roles */
            $roles = $this->roles->paginateWithPermissions(
                $filters, $pageSize, $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload' => [
                    'success' => true,
                    'data' => $roles->items(),
                    'pagination' => [
                        'total' => $roles->total(),
                        'per_page' => $roles->perPage(),
                        'current_page' => $roles->currentPage(),
                        'last_page' => $roles->lastPage(),
                        'from' => $roles->firstItem(),
                        'to' => $roles->lastItem()
                    ]
                ]
            ];
        }

        /** @var Collection $roles */
        $roles = $this->roleRepository->getAllWithPermissions($filters);
        
        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data' => $roles,
                'count' => $roles->count()
            ]
        ];
    }

    public function show(int $id): array
    {
        $role = $this->roles->findWithPermissionsById($id);

        if (!$role) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'Role not found',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $role
            ]
        ];
    }

    public function store(array $data): array
    {
        $existing = $this->roles->findSoftDeletedByName($data['name']);
        $permissions = $data['permissions'] ?? [];

        if ($existing && $existing->trashed()) {
            $existing->restore();

            $existing->guard_name = 'api';
            $this->roles->save($existing);

            $existing->syncPermissions($permissions);
            $existing->refresh();

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'message' => 'Role restored and updated successfully',
                    'data'    => $existing
                ]
            ];
        }

        $role = $this->roles->create([
            'name' => $data['name'],
            'guard_name' => 'api'
        ]);

        $role->syncPermissions($permissions);
        $role->refresh();

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'message' => 'Role created successfully',
                'data'    => $role
            ]
        ];
    }

    public function update(Role $role, array $data): array
    {
        $permissions = $data['permissions'] ?? [];

        $role->name = $data['name'];
        $this->roles->save($role);

        $role->syncPermissions($permissions);
        $role->refresh();

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'Role updated successfully',
                'data'    => $role
            ]
        ];
    }

    public function destroy(Role $role): array
    {
        $this->roles->delete($role);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'Role deleted successfully'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->roles->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "{$deletedCount} roles deleted successfully"
            ]
        ];
    }

    public function nameConflictWithDeleted(string $name, int $excludingId): bool
    {
        return $this->roles->nameConflictWithDeleted($name, $excludingId);
    }
}