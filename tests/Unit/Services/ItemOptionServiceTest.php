<?php

namespace Tests\Unit\Services;

use App\Models\ItemOption;
use App\Repositories\Contracts\ItemOptionRepositoryInterface;
use App\Services\ItemOptionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class ItemOptionServiceTest extends TestCase
{
    /** @test */
    public function it_returns_not_found_for_missing_option()
    {
        $mockRepo = Mockery::mock(ItemOptionRepositoryInterface::class);
        $mockRepo->shouldReceive('findById')->with(999)->andReturn(null);

        $result = (new ItemOptionService($mockRepo))->findItemOptionById(999);

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    /** @test */
    public function it_returns_paginated_options_when_requested()
    {
        $paginator = new LengthAwarePaginator(
            items: collect([new ItemOption(), new ItemOption()])->take(1),
            total: 2,
            perPage: 1,
            currentPage: 1,
        );

        $mockRepo = Mockery::mock(ItemOptionRepositoryInterface::class);
        $mockRepo->shouldReceive('paginate')->andReturn($paginator);

        $result = (new ItemOptionService($mockRepo))->list(['pagesize' => 1, 'pagenumber' => 1]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(1, $result['payload']['data']);
        $this->assertSame(2, $result['payload']['pagination']['total']);
    }
}
