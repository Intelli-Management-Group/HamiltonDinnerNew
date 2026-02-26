<?php

namespace Tests\Unit\Services;

use App\Models\ItemPreference;
use App\Repositories\Contracts\ItemPreferenceRepositoryInterface;
use App\Services\ItemPreferenceService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class ItemPreferenceServiceTest extends TestCase
{
    /** @test */
    public function it_returns_not_found_for_missing_preference()
    {
        $mockRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $mockRepo->shouldReceive('findById')->with(999)->andReturn(null);

        $result = (new ItemPreferenceService($mockRepo))->findItemById(999);

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    /** @test */
    public function it_lists_preferences_without_pagination()
    {
        $mockRepo = Mockery::mock(ItemPreferenceRepositoryInterface::class);
        $mockRepo->shouldReceive('getAll')->andReturn(new Collection([new ItemPreference()]));

        $result = (new ItemPreferenceService($mockRepo))->list([]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(1, $result['payload']['data']);
    }
}
