<?php

namespace Tests\Unit\Services;

use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\RoleService;
use Mockery;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    /** @test */
    public function it_returns_not_found_for_missing_role()
    {
        $mockRepo = Mockery::mock(RoleRepositoryInterface::class);
        $mockRepo->shouldReceive('findById')->with(999)->andReturn(null);

        $result = (new RoleService($mockRepo))->findRoleById(999);

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    /** @test */
    public function it_detects_name_conflict_with_deleted_role()
    {
        $mockRepo = Mockery::mock(RoleRepositoryInterface::class);
        $mockRepo->shouldReceive('nameConflictWithDeleted')->andReturn(true);

        $this->assertTrue((new RoleService($mockRepo))->nameConflictWithDeleted('Temp', 0));
    }
}
