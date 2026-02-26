<?php

namespace App\Repositories\Contracts;

interface PermissionRepositoryInterface extends BaseRepositoryInterface
{
    public function getAllNames(): array;
}
