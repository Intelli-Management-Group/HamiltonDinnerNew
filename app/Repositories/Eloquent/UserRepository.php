<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private User $model
    ) {}

    public function findById($id): ?User
    {
        return $this->model->find($id);
    }

    public function findWithPermissionsById($id): ?User
    {
        // withPermissions() is a local scope defined in the User model
        // to eager load permissions relationship.
        // Please refer to scopeWithPermissions() in User model for details.
        return $this->model->withPermissions()->find($id);
    }

    public function queryWithPermissions(array $filters = []): Builder
    {
        // search() is a local scope defined in the User model
        // for filtering based on search criteria.
        // Please refer to scopeSearch() in User model for details.
        return $this->model->withPermissions()
            ->search($filters['search'] ?? null)
            ->latest();
    }

    public function paginateWithPermissions(
        array $filters = [], 
        int $pageSize = 15, 
        int $pageNumber = 1
    ): LengthAwarePaginator
    {
        return $this->queryWithPermissions($filters)
            ->paginate($pageSize, ['*'], 'page', $pageNumber);
    }

    public function getAllWithPermissions(array $filters = []): Collection
    {
        return $this->queryWithPermissions($filters)->get();
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
}