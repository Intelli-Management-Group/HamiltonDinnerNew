<?php

namespace App\Repositories\Contracts;

interface RoomDetailRepositoryInterface extends BaseRepositoryInterface
{
    /** Return all non-null APNs device tokens for active rooms. */
    public function getAllDeviceTokens(): array;
}

