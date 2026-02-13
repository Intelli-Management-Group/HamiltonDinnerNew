<?php

namespace Tests\Unit\Models;

use App\Models\CategoryDetail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function sets_parent_id_to_zero_when_null()
    {
        $category = CategoryDetail::create([
            'cat_name' => 'Test Category',
            'category_chinese_name' => '测试类别',
            'type' => 'test',
        ]);

        $this->assertEquals(0, $category->parent_id);
    }

    /** @test */
    public function can_scope_parent_categories()
    {
        $parent = CategoryDetail::create([
            'cat_name' => 'Parent Category',
            'category_chinese_name' => '父类别',
            'type' => 'test',
        ]);

        CategoryDetail::create([
            'cat_name' => 'Child Category',
            'category_chinese_name' => '子类别',
            'type' => 'test',
            'parent_id' => $parent->id,
        ]);

        $parents = CategoryDetail::parent()->get();

        $this->assertCount(1, $parents);
        $this->assertEquals('Parent Category', $parents->first()->cat_name);
    }

    /** @test */
    public function can_scope_categories_by_type()
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

        $typeACategories = CategoryDetail::type('A')->get();
        $this->assertCount(1, $typeACategories);
        $this->assertEquals('Type A Category', $typeACategories->first()->cat_name);
    }

    /** @test */
    public function category_detail_relations_are_configured()
    {
        $category = new CategoryDetail();

        $this->assertInstanceOf(HasOne::class, $category->parentId());
        $this->assertInstanceOf(BelongsTo::class, $category->catParentId());
    }
}