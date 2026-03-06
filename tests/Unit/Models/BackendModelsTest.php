<?php

namespace Tests\Unit\Models;

use App\Models\BackendPermission;
use App\Models\BackendRole;
use App\Models\BackendUser;
use App\Models\RoleHasPermissions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BackendModelsTest extends TestCase
{
    #[Test]
    public function backend_permission_can_be_instantiated()
    {
        $this->assertInstanceOf(BackendPermission::class, new BackendPermission());
    }

    #[Test]
    public function role_has_permissions_can_be_instantiated()
    {
        $this->assertInstanceOf(RoleHasPermissions::class, new RoleHasPermissions());
    }

    #[Test]
    public function backend_role_relations_are_configured()
    {
        $role = new BackendRole();

        $this->assertInstanceOf(HasMany::class, $role->users());
        $this->assertInstanceOf(HasManyThrough::class, $role->permissions());
    }

    #[Test]
    public function backend_user_relations_are_configured()
    {
        $user = new BackendUser();

        $this->assertInstanceOf(HasOne::class, $user->role());
        $this->assertInstanceOf(HasManyThrough::class, $user->permissions());
    }
}
