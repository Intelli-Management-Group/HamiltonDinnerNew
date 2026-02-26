<?php

namespace App\Repositories\Contracts;

use App\Models\MenuDetail;

interface MenuDetailRepositoryInterface extends BaseRepositoryInterface
{
    public function findByDate(string $date): ?MenuDetail;

    public function findLatest(): ?MenuDetail;

    public function findLatestDate(): ?string;

    public function findSoftDeletedByDate(string $date): ?MenuDetail;
}