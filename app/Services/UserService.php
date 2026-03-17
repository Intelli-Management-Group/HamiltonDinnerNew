<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles
    ) {}

    public function findUserById(int $id): array
    {
        $user = $this->users->findById($id);

        if (!$user) {
            return $this->errorResponse(
                message: 'User not found.',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($user);
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

            /** @var LengthAwarePaginator $users */
            $users = $this->users->paginate(
                $filters,
                ['permissionList'],
                $pageSize,
                $pageNumber
            );

            return $this->paginatedResponse($users);
        }

        /** @var Collection $users */
        $users = $this->users->getAll(
            filters: $filters,
            relations: ['permissionList']
        );

        return $this->collectionResponse($users);
    }

    public function show(int $id): array
    {
        $user = $this->users->findById($id, relations: ['permissionList']);

        if (!$user) {
            return $this->errorResponse(
                message: 'User not found.',
                statusCode: 404,
                data: null,
                includeData: false
            );
        }

        return $this->successResponse($user);
    }

    public function store(array $data): array
    {
        // Check for a soft-deleted user
        $existing = $this->users->findSoftDeletedByEmail($data['email']);

        // Restore the soft-deleted user if found
        if ($existing && $existing->trashed()) {
            $existing->restore();

            $updateData = [
                'name' => $data['name'],
                'user_name' => $data['user_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => $data['role_id'],
                'role' => $data['role'] ?? null,
                'email_verified_at' => $data['email_verified_at'] ?? null,
                'is_admin' => $data['is_admin'] ?? false,
                'avatar' => $data['avatar'] ?? null,
            ];

            $existing->fill($updateData);
            $this->users->save($existing);

            $role = $this->$roles->findById($data['role_id']);
            $existing->syncRoles([$role]);

            return $this->successResponse(
                data: $existing,
                message: 'User restored and updated successfully',
                statusCode: 201
            );
        }
        
        // Otherwise, create a new user
        $user = $this->users->create([
            'name' => $data['name'],
            'user_name' => $data['user_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'role' => $data['role'] ?? null,
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'is_admin' => $data['is_admin'] ?? false,
            'avatar' => $data['avatar'] ?? null,
        ]);

        $role = $this->roles->findById($data['role_id']);
        $user->assignRole($role);

        return $this->successResponse(
            data: $user,
            message: 'User created successfully',
            statusCode: 201
        );
    }

    public function update(User $user, array $data): array
    {
        // whitelist allowed fields for update
        $updateData = array_intersect_key(
            $data,
            array_flip([
                'name',
                'user_name',
                'email',
                'password',
                'role_id',
                'role',
                'email_verified_at',
                'is_admin', 
                'avatar'
            ])
        );

        if (isset($updateData['password']) && $updateData['password'] !== '') {
            $updateData['password'] = Hash::make($updateData['password']);
        }

        // Note: small model calls like fill->save are fine
        $user->fill($updateData);
        $this->users->save($user);

        if (isset($data['role_id'])) {
            $role = $this->roles->findById($data['role_id']);
            $user->assignRole($role);
        }

        return $this->successResponse(
            data: $user->fresh(['roles']),
            message: 'User updated successfully'
        );
    }

    public function destroy(User $user): array
    {
        $this->users->delete($user);

        return $this->successResponse(
            message: 'User deleted successfully',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $count = $this->users->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "$count users deleted successfully",
            statusCode: 200,
            includeData: false
        );
    }
}