<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById($id): ?User;
    public function findWithPermissionsById($id): ?User;
    public function create(array $data): User;
    public function save(User $user): User;
    public function delete(User $user): bool;
}