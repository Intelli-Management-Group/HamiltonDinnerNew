<?php

namespace Tests\Unit\Services;

use App\Models\CategoryDetail;
use App\Repositories\Contracts\CategoryDetailRepositoryInterface;
use App\Services\CategoryDetailService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class CategoryDetailServiceTest extends TestCase
{
    /** @test */
    public function it_returns_not_found_for_missing_category()
    {
        $mockRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('findById')->with(999)->andReturn(null);

        $result = (new CategoryDetailService($mockRepo))->findCategoryById(999);

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    /** @test */
    public function it_returns_paginated_categories_when_requested()
    {
        $paginator = new LengthAwarePaginator(
            items: collect([new CategoryDetail(), new CategoryDetail()])->take(1),
            total: 2,
            perPage: 1,
            currentPage: 1,
        );

        $mockRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('paginate')->andReturn($paginator);

        $result = (new CategoryDetailService($mockRepo))->list(['pagesize' => 1, 'pagenumber' => 1]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(1, $result['payload']['data']);
        $this->assertSame(2, $result['payload']['pagination']['total']);
    }

    /** @test */
    public function it_bulk_deletes_categories()
    {
        $mockRepo = Mockery::mock(CategoryDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('bulkDeleteByIds')->with([1, 2])->andReturn(2);

        $result = (new CategoryDetailService($mockRepo))->bulkDestroy([1, 2]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertSame('2 CategoryDetails deleted successfully.', $result['payload']['message']);
    }
}
