<?php

namespace Tests\Unit\Repositories;

use App\Models\CategoryDetail;
use App\Models\ItemDetail;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TestBaseRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TestBaseRepository(new ItemDetail());
    }

    /** @test */
    public function it_applies_allowed_relations_only()
    {
        $category = CategoryDetail::create([
            'cat_name' => 'Breakfast',
            'category_chinese_name' => '早餐',
            'type' => 'B',
            'parent_id' => 0,
        ]);

        $item = ItemDetail::create([
            'cat_id' => $category->id,
            'item_name' => 'Oatmeal',
            'item_chinese_name' => '燕麦',
            'is_allday' => 0,
        ]);

        $found = $this->repository->findById($item->id, relations: ['category', 'not_allowed']);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('category'));
        $this->assertFalse($found->relationLoaded('not_allowed'));
    }

    /** @test */
    public function it_orders_results_when_order_by_is_provided()
    {
        ItemDetail::create([
            'cat_id' => 1,
            'item_name' => 'Apple',
            'item_chinese_name' => '苹果',
            'is_allday' => 0,
        ]);

        ItemDetail::create([
            'cat_id' => 1,
            'item_name' => 'Banana',
            'item_chinese_name' => '香蕉',
            'is_allday' => 0,
        ]);

        $results = $this->repository->getAll([
            'order_by' => 'item_name',
            'order_direction' => 'desc',
        ]);

        $this->assertCount(2, $results);
        $this->assertSame('Banana', $results->first()->item_name);
    }

    /** @test */
    public function it_filters_soft_deleted_records_when_deleted_at_is_specified()
    {
        $active = ItemDetail::create([
            'cat_id' => 1,
            'item_name' => 'Toast',
            'item_chinese_name' => '吐司',
            'is_allday' => 0,
        ]);

        $deleted = ItemDetail::create([
            'cat_id' => 1,
            'item_name' => 'Soup',
            'item_chinese_name' => '汤',
            'is_allday' => 0,
        ]);

        $deleted->delete();

        $withoutDeleted = $this->repository->getAll(['deleted_at' => null]);
        $onlyDeleted = $this->repository->getAll(['deleted_at' => 'only']);
        $withDeleted = $this->repository->getAll(['deleted_at' => 'with']);
        $excludedDeleted = $this->repository->getAll(['deleted_at' => 'exclude']);
        $withoutExplicit = $this->repository->getAll(['deleted_at' => 'without']);

        $this->assertCount(1, $withoutDeleted);
        $this->assertSame($active->id, $withoutDeleted->first()->id);

        $this->assertCount(1, $onlyDeleted);
        $this->assertSame($deleted->id, $onlyDeleted->first()->id);

        $this->assertCount(2, $withDeleted);
        $this->assertCount(1, $excludedDeleted);
        $this->assertSame($active->id, $excludedDeleted->first()->id);

        $this->assertCount(1, $withoutExplicit);
        $this->assertSame($active->id, $withoutExplicit->first()->id);
    }
}

class TestBaseRepository extends BaseRepository
{
    protected const ALLOWED_RELATIONS = ['category'];

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (array_key_exists('item_name', $filters)) {
            $query->where('item_name', $filters['item_name']);
        }

        return $query;
    }
}
