<?php

namespace App\Repositories\Contracts;

use App\Models\Setting;

interface SettingRepositoryInterface extends BaseRepositoryInterface
{
    public function findByKey($key): ?Setting;

    public function getAllKeyValues(): array;
}