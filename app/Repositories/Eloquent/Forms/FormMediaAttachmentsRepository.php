<?php

namespace App\Repositories\Eloquent\Forms;

use App\Models\FormMediaAttachments;
use App\Repositories\Contracts\Forms\FormMediaAttachmentsRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class FormMediaAttachmentsRepository extends BaseRepository implements FormMediaAttachmentsRepositoryInterface
{
    public function __construct(
        FormMediaAttachments $model
    ) {
        parent::__construct($model);
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        return $query->latest();
    }
}