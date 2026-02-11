<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    // Allowed relations and scopes for eager loading
    private const ALLOWED_RELATIONS = [
        'role',
        'permissions',
    ];

    public function __construct(
        private User $model
    ) {}

    public function findById(
        int $id,
        array $filters = [],
        array $relations = [],
    ): ?User
    {
        return $this->query($filters, $relations)->find($id);
    }

    public function query(
        array $filters = [],
        array $relations = [],
    ): Builder
    {
        $query = $this->model->newQuery();

        // Eager load allowed relations
        $query = $this->applyRelations($query, $relations);

        if (!empty($filters['role_id'])) {
            if (is_array($filters['role_id'])) {
                $query->whereIn('role_id', $filters['role_id']);
            } else {
                $query->where('role_id', $filters['role_id']);
            }
        }

        if (!empty($filters['deleted_at'])) {
            if ($filters['deleted_at'] === 'only') {
                $query->onlyTrashed();
            } elseif ($filters['deleted_at'] === 'with') {
                $query->withTrashed();
            } elseif ($filters['deleted_at'] === 'without') {
                $query->whereNull('deleted_at');
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
    
    public function paginate(
        array $filters = [],
        array $relations = [],
        int $pageSize = 15,
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->query($filters, $relations)
            ->paginate($pageSize, ['*'], 'page', $pageNumber);
    }

    public function getAll(
        array $filters = [],
        array $relations = []
    ): Collection
    {
        return $this->query($filters, $relations)->get();
    }

    public function findSoftDeletedByEmail(string $email): ?User
    {
        // Using withTrashed() to include soft-deleted records in the query
        return $this->model->withTrashed()
            ->where('email', $email)
            ->first();
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

    public function bulkDeleteByIds(array $ids): int
    {
        return $this->model
            ->whereIn('id', $ids)
            ->delete();
    }

    // Helper functions to apply filters, relations, and scopes
    private function applyRelations(
        Builder $query,
        array $relations
    ): Builder
    {
        $safe_relations = array_values(array_intersect($relations, self::ALLOWED_RELATIONS));

        if (!empty($safe_relations)) {
            $query->with($safe_relations);
        }

        return $query;
    }
}