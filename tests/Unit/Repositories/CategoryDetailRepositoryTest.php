<?php

namespace Tests\Unit\Repositories;

use App\Models\CategoryDetail;
use App\Repositories\Eloquent\CategoryDetailRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryDetailRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CategoryDetailRepository $categoryDetails;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categoryDetails = new CategoryDetailRepository(new CategoryDetail());
    }

    /** @test */
    public function can_find_category_by_id()
    {
        $category = CategoryDetail::create([
            'cat_name' => 'Test Category',
            'category_chinese_name' => '测试类别',
            'type' => 'A',
            'parent_id' => 0,
        ]);

        $foundCategory = $this->categoryDetails->findById($category->id);

        $this->assertNotNull($foundCategory);
        $this->assertEquals($category->id, $foundCategory->id);
    }
    
    /** @test */
    public function returns_null_when_category_not_found()
    {
        $foundCategory = $this->categoryDetails->findById(999);
        $this->assertNull($foundCategory);
    }

    /** @test */
    public function can_query_categories_by_type()
    {
        CategoryDetail::create([
            'cat_name' => 'Type A Category',
            'category_chinese_name' => '类别A',
            'type' => 'A',
            'parent_id' => 0,
        ]);

        CategoryDetail::create([
            'cat_name' => 'Type B Category',
            'category_chinese_name' => '类别B',
            'type' => 'B',
            'parent_id' => 0,
        ]);

        $query = $this->categoryDetails->queryWithType(['type' => 'A']);
        $categories = $query->get();
        $this->assertCount(1, $categories);
        $this->assertEquals('A', $categories->first()->type);
    }

    /** @test */
    public function filters_categories_by_type()
    {
        CategoryDetail::create([
            'cat_name' => 'Breakfast',
            'category_chinese_name' => '早餐',
            'type' => 'B',
            'parent_id' => 0,
        ]);

        CategoryDetail::create([
            'cat_name' => 'Lunch',
            'category_chinese_name' => '午餐',
            'type' => 'L',
            'parent_id' => 0,
        ]);

        $results = $this->categoryDetails->getAllWithType(['type' => 'B']);

        $this->assertCount(1, $results);
        $this->assertEquals('Breakfast', $results->first()->cat_name);
    }

    /** @test */
    public function paginates_filtered_categories()
    {
        foreach (range(1, 3) as $i) {
            $category = CategoryDetail::create([
                'cat_name' => "Category {$i}",
                'category_chinese_name' => "分类 {$i}",
                'type' => 'B',
                'parent_id' => 0,
            ]);

            $timestamp = Carbon::now()->subMinutes(4 - $i);
            $category->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();
        }

        $page = $this->categoryDetails->paginateWithType(['type' => 'B'], perPage: 2, pageNumber: 2);
        
        $this->assertEquals(3, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertEquals('Category 1', $page->items()[0]->cat_name); // latest() ordering
    }

    /** @test */
    public function creates_and_deletes_categories()
    {
        $category = $this->categoryDetails->create([
            'cat_name' => 'Snack',
            'category_chinese_name' => '零食',
            'type' => 'S',
            'parent_id' => 0,
        ]);

        $this->assertDatabaseHas('category_details', ['id' => $category->id]);

        $deleted = $this->categoryDetails->delete($category);

        $this->assertTrue($deleted);
        $this->assertSoftDeleted('category_details', ['id' => $category->id]);
    }
}