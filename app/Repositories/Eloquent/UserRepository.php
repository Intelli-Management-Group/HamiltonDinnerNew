<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private User $model
    ) {}

    public function findById($id): ?User
    {
        return $this->model->find($id);
    }

    public function findWithPermissionsById($id): ?User
    {
        return $this->model->with('permissionList')->find($id);
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function save(User $user): User
    {
        $user->save(); // Built-in Eloquent save method
        return $user;
    }

    public function delete(User $user): bool
    {
        return $user->delete(); // Built-in Eloquent delete method
    }
}