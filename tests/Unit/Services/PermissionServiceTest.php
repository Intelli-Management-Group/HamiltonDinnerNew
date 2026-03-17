<?php

namespace Tests\Unit\Services;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Services\PermissionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PermissionServiceTest extends TestCase
{
    #[Test]
    public function it_returns_paginated_permissions_when_requested()
    {
        $paginator = new LengthAwarePaginator(
            items: collect([new Permission(), new Permission()])->take(1),
            total: 2,
            perPage: 1,
            currentPage: 1,
        );

        $mockRepo = Mockery::mock(PermissionRepositoryInterface::class);
        $mockRepo->shouldReceive('paginate')->andReturn($paginator);

        $result = (new PermissionService($mockRepo))->list(['pagesize' => 1, 'pagenumber' => 1]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(1, $result['payload']['data']);
        $this->assertSame(2, $result['payload']['pagination']['total']);
    }
}
