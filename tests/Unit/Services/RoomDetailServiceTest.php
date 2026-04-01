<?php

namespace Tests\Unit\Services;

use App\Models\RoomDetail;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use App\Services\RoomDetailService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RoomDetailServiceTest extends TestCase
{
    #[Test]
    public function it_returns_not_found_for_missing_room()
    {
        $mockRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('findById')->with(999)->andReturn(null);

        $result = (new RoomDetailService($mockRepo))->findRoomById(999);

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    #[Test]
    public function it_returns_paginated_rooms_with_active_filter()
    {
        // 1 active room out of 1 total (the filter is applied inside the repo, which is mocked)
        $paginator = new LengthAwarePaginator(
            items: collect([new RoomDetail()]),
            total: 1,
            perPage: 1,
            currentPage: 1,
        );

        $mockRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('paginate')->andReturn($paginator);

        $result = (new RoomDetailService($mockRepo))->list([
            'is_active' => 1,
            'pagesize'  => 1,
            'pagenumber' => 1,
        ]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(1, $result['payload']['data']);
        $this->assertSame(1, $result['payload']['pagination']['total']);
    }

    #[Test]
    public function it_soft_deletes_a_room()
    {
        $room = new RoomDetail();

        $mockRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('delete')->once()->with($room);

        $result = (new RoomDetailService($mockRepo))->destroy($room);

        $this->assertSame(200, $result['statusCode']);
        $this->assertTrue($result['payload']['success']);
        $this->assertSame('RoomDetail deleted successfully.', $result['payload']['message']);
    }

    #[Test]
    public function it_bulk_deletes_rooms_by_ids()
    {
        $mockRepo = Mockery::mock(RoomDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('bulkDeleteByIds')->once()->with([1, 2, 3])->andReturn(3);

        $result = (new RoomDetailService($mockRepo))->bulkDestroy([1, 2, 3]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertTrue($result['payload']['success']);
        $this->assertStringContainsString('3', $result['payload']['message']);
    }
}
