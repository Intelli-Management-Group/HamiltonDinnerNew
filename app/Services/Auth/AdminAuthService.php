<?php

namespace App\Services\Auth;

use App\Models\Permission;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;

class AdminAuthService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PermissionRepositoryInterface $permissions
    ) {}

    // Service methods utilizing $this->users for user data operations
    public function login(array $credentials): ?array
    {
        if (!$token = auth()->attempt($credentials)) {
            return [
                'statusCode' => 201,
                'payload' => ['error' => 'Email or Password is incorrect'],
            ];
        }

        $user = auth()->user();

        $allPermissionsResult = $this->permissions->getAllNames();
        $allPermissions = array_fill_keys($allPermissionsResult, 0);

        $loggedInUser = $this->users->findWithPermissionsById($user->id)?->toArray();
        if ($loggedInUser) {
            foreach ($loggedInUser['permission_list'] as $permission) {
                $allPermissions[$permission['name']] = 1;
            }
        }

        return [
            'statusCode' => 200,
            'payload' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60, // Use config value instead of factory method
                'user' => $user,
                'permissions' => $allPermissions,
                'ResponseCode' => 1,
                'ResponseText' => 'success',
            ]
        ];
    }

    public function logout(): array
    {
        auth()->logout();

        return [
            'statusCode' => 200,
            'payload' => [
                'message' => 'Successfully logged out',
            ]
        ];
    }

    public function register(array $data): array
    {
        $data['password'] = bcrypt($data['password']);

        $user = $this->users->create($data);

        return [
            'statusCode' => 201,
            'payload' => [
                'message' => 'User successfully registered',
                'user' => $user,
            ]
        ];
    }

    public function refresh(): array
    {
        // If token is invalid/expired, JWTAuth will throw.
        // Let the controller catch this, and respond 500 or map to 401 if needed.
        $newToken = auth()->refresh();
        $user = auth('api')->user();

        return [
            'statusCode' => 200,
            'payload' => [
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60, // Use config value instead of factory method
                'user' => $user,
            ]
        ];
    }
}