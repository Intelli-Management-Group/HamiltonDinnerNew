<?php

namespace Tests\Unit\Services;

use App\Models\ItemDetail;
use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use App\Services\ItemDetailService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class ItemDetailServiceTest extends TestCase
{
    /** @test */
    public function it_returns_not_found_for_missing_item()
    {
        $mockRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('findById')->with(999)->andReturn(null);

        $result = (new ItemDetailService($mockRepo))->findItemById(999);

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    /** @test */
    public function it_returns_paginated_items_when_requested()
    {
        $paginator = new LengthAwarePaginator(
            items: collect([new ItemDetail(), new ItemDetail()])->take(1),
            total: 2,
            perPage: 1,
            currentPage: 1,
        );

        $mockRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('paginate')->andReturn($paginator);

        $result = (new ItemDetailService($mockRepo))->list(['pagesize' => 1, 'pagenumber' => 1]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(1, $result['payload']['data']);
        $this->assertSame(2, $result['payload']['pagination']['total']);
    }

    /** @test */
    public function it_bulk_deletes_items()
    {
        $mockRepo = Mockery::mock(ItemDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('bulkDeleteByIds')->with([1, 2])->andReturn(2);

        $result = (new ItemDetailService($mockRepo))->bulkDestroy([1, 2]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertSame('2 ItemDetails deleted successfully.', $result['payload']['message']);
    }
}
