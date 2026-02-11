<?php

namespace App\Repositories\Contracts\Forms;

use App\Models\FormMediaAttachments;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
    
interface FormMediaAttachmentsRepositoryInterface
{
    public function findById($id): ?FormMediaAttachments;

    public function query(array $filters = []): Builder;

    public function paginate(
        array $filters = [],
        int $perPage = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator;

    public function getAll(array $filters = []): Collection;

    public function create(array $data): FormMediaAttachments;

    public function save(FormMediaAttachments $formMediaAttachments): FormMediaAttachments;
    public function delete(FormMediaAttachments $formMediaAttachments): bool;

    public function bulkDeleteByIds(array $ids): int;
}