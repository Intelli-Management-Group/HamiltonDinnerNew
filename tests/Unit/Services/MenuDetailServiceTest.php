<?php

namespace Tests\Unit\Services;

use App\Models\MenuDetail;
use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use App\Services\MenuDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MenuDetailServiceTest extends TestCase
{
    // RefreshDatabase is required for the restore test, which exercises Eloquent
    // model lifecycle methods (restore, refresh) that must run against the DB.
    use RefreshDatabase;

    /** @test */
    public function it_returns_not_found_for_missing_menu()
    {
        $mockRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('findById')->with(999)->andReturn(null);

        $result = (new MenuDetailService($mockRepo))->findMenuById(999);

        $this->assertSame(404, $result['statusCode']);
        $this->assertFalse($result['payload']['success']);
    }

    /** @test */
    public function it_restores_soft_deleted_menu_by_date()
    {
        $menu = MenuDetail::create([
            'date'     => now()->toDateString(),
            'items'    => ['1'],
            'is_allday' => false,
        ]);
        $menu->delete();

        $softDeleted = MenuDetail::withTrashed()->find($menu->id);
        $date = $softDeleted->date->toDateString();

        $mockRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
        $mockRepo->shouldReceive('findSoftDeletedByDate')->with($date)->andReturn($softDeleted);
        // Delegate to the model's own save() so that refresh() can read the updated data.
        $mockRepo->shouldReceive('save')->once()->andReturnUsing(fn($m) => tap($m, fn($m) => $m->save()));

        $result = (new MenuDetailService($mockRepo))->store([
            'date'     => $date,
            'items'    => ['2'],
            'is_allday' => true,
        ]);

        $this->assertSame(200, $result['statusCode']);
        $this->assertSame('MenuDetail restored and updated successfully.', $result['payload']['message']);
        $this->assertSame(['2'], $result['payload']['data']->items);
    }
}
