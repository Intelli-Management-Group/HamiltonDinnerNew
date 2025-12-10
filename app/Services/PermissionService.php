<?php

namespace App\Services;

use App\Repositories\Contracts\PermissionRepositoryInterface;

class PermissionService
{
    public function __construct(
        private PermissionRepositoryInterface $permissionRepository
    )
    {}
    

}