<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $users
    ) {}

    public function findUserById(int $id): array
    {
        $user = $this->users->findById($id);

        if (!$user) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'User not found.',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $user
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

            /** @var LengthAwarePaginator $users */
            $users = $this->users->paginateWithPermissions(
                $filters, $pageSize, $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload' => [
                    'success' => true,
                    'data' => $users->items(),
                    'pagination' => [
                        'total' => $users->total(),
                        'per_page' => $users->perPage(),
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem()
                    ]
                ]
            ];
        }

        /** @var Collection $users */
        $users = $this->users->getAllWithPermissions($filters);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data' => $users,
                'count' => $users->count()
            ]
        ];
    }

    public function show(int $id): array
    {
        $user = $this->users->findWithPermissionsById($id);

        if (!$user) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'User not found.'
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $user
            ]
        ];
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

            $role = Role::findById($data['role_id']);
            $existing->syncRoles([$role]);

            return [
                'statusCode' => 201,
                'payload'    => [
                    'success' => true,
                    'message' => 'User restored and updated successfully',
                    'data'    => $existing
                ]
            ];
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

        $role = Role::findById($data['role_id']);
        $user->assignRole($role);

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'message' => 'User created successfully',
                'data'    => $user
            ]
        ];
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
            $role = Role::findById($data['role_id']);
            $user->assignRole($role);
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'User updated successfully',
                'data'    => $user->fresh(['roles'])
            ]
        ];
    }

    public function destroy(User $user): array
    {
        $this->users->delete($user);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'User deleted successfully'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $count = $this->users->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "$count users deleted successfully"
            ]
        ];
    }
}