<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\SettingService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SettingServiceTest extends TestCase
{
    #[Test]
    public function it_returns_not_found_for_missing_setting_key()
    {
        $mockRepo = Mockery::mock(SettingRepositoryInterface::class);
        $mockRepo->shouldReceive('findByKey')->with('missing.key')->andReturn(null);

        $result = (new SettingService($mockRepo))->findSettingByKey('missing.key');

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    #[Test]
    public function it_returns_paginated_settings_when_requested()
    {
        $paginator = new LengthAwarePaginator(
            items: collect([new Setting(), new Setting()])->take(1),
            total: 2,
            perPage: 1,
            currentPage: 1,
        );

        $mockRepo = Mockery::mock(SettingRepositoryInterface::class);
        $mockRepo->shouldReceive('paginate')->andReturn($paginator);

        $result = (new SettingService($mockRepo))->list(['per_page' => 1, 'page' => 1]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(1, $result['payload']['data']);
        $this->assertSame(2, $result['payload']['meta']['total']);
    }
}
