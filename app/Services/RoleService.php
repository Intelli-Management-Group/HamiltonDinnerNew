<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoleService extends BaseService
{
    public function __construct(
        private RoleRepositoryInterface $roles
    ) {}

    public function findRoleById(int $id): array
    {
        $role = $this->roles->findById($id);

        if (!$role) {
            return $this->errorResponse(
                message: 'Role not found.',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($role);
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
            $roles = $this->roles->paginate(
                $filters,
                [],
                $pageSize,
                $pageNumber
            );

            return $this->paginatedResponse($roles);
        }

        /** @var Collection $roles */
    $roles = $this->roles->getAll($filters);

        return $this->collectionResponse($roles);
    }

    public function show(int $id): array
    {
        $role = $this->roles->findWithPermissionsById($id);

        if (!$role) {
            return $this->errorResponse(
                message: 'Role not found',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($role);
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

            return $this->successResponse(
                data: $existing,
                message: 'Role restored and updated successfully'
            );
        }

        $role = $this->roles->create([
            'name' => $data['name'],
            'guard_name' => 'api'
        ]);

        $role->syncPermissions($permissions);
        $role->refresh();

        return $this->successResponse(
            data: $role,
            message: 'Role created successfully',
            statusCode: 201
        );
    }

    public function update(Role $role, array $data): array
    {
        $permissions = $data['permissions'] ?? [];

        $role->name = $data['name'];
        $this->roles->save($role);

        $role->syncPermissions($permissions);
        $role->refresh();

        return $this->successResponse(
            data: $role,
            message: 'Role updated successfully'
        );
    }

    public function destroy(Role $role): array
    {
        $this->roles->delete($role);

        return $this->successResponse(
            message: 'Role deleted successfully',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->roles->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} roles deleted successfully",
            statusCode: 200,
            includeData: false
        );
    }

    public function nameConflictWithDeleted(string $name, int $excludingId): bool
    {
        return $this->roles->nameConflictWithDeleted($name, $excludingId);
    }
}