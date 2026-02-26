<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    // Allowed relations and scopes for eager loading
    protected const ALLOWED_RELATIONS = [
        'role',
        'permissionList',
    ];

    public function __construct(
        User $model
    ) {
        parent::__construct($model);
    }

    public function findSoftDeletedByEmail(string $email): ?User
    {
        // Using withTrashed() to include soft-deleted records in the query
        return $this->model->withTrashed()
            ->where('email', $email)
            ->first();
    }

    protected function applyFilters(
        Builder $query,
        array $filters
    ): Builder
    {
        if (array_key_exists('user_name', $filters)) {
            $query->where('user_name', $filters['user_name']);
        }

        if (!empty($filters['role_id'])) {
            if (is_array($filters['role_id'])) {
                $query->whereIn('role_id', $filters['role_id']);
            } else {
                $query->where('role_id', $filters['role_id']);
            }
        }

        // Apply allowed scopes
        // search() is a local scope defined in the User model
        // for filtering based on search criteria.
        // Please refer to scopeSearch() in User model for details.
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->latest();
    }
}