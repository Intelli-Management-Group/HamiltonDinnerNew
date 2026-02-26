<?php

namespace Tests\Unit\Repositories;

use App\Models\CategoryDetail;
use App\Models\ItemDetail;
use App\Models\ItemOption;
use App\Models\ItemPreference;
use App\Models\MenuDetail;
use App\Repositories\Eloquent\ItemDetailRepository;
use App\Repositories\Eloquent\ItemOptionRepository;
use App\Repositories\Eloquent\ItemPreferenceRepository;
use App\Repositories\Eloquent\MenuDetailRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    private ItemDetailRepository $itemDetails;
    private ItemOptionRepository $itemOptions;
    private ItemPreferenceRepository $itemPreferences;
    private MenuDetailRepository $menuDetails;

    protected function setUp(): void
    {
        parent::setUp();
        $this->itemDetails = new ItemDetailRepository(new ItemDetail());
        $this->itemOptions = new ItemOptionRepository(new ItemOption());
        $this->itemPreferences = new ItemPreferenceRepository(new ItemPreference());
        $this->menuDetails = new MenuDetailRepository(new MenuDetail());
    }

    /** @test */
    public function it_filters_item_details_by_category_id()
    {
        ItemDetail::create([
            'cat_id' => 1,
            'item_name' => 'Oatmeal',
            'item_chinese_name' => '燕麦',
            'is_allday' => 0,
        ]);

        ItemDetail::create([
            'cat_id' => 2,
            'item_name' => 'Soup',
            'item_chinese_name' => '汤',
            'is_allday' => 0,
        ]);

    $results = $this->itemDetails->getAll(['cat_id' => 1]);

        $this->assertCount(1, $results);
        $this->assertSame('Oatmeal', $results->first()->item_name);
    }

    /** @test */
    public function it_returns_order_report_summaries_in_category_order()
    {
        $first = ItemDetail::create([
            'cat_id' => 1,
            'item_name' => 'Toast',
            'item_chinese_name' => '吐司',
            'is_allday' => 0,
        ]);

        $second = ItemDetail::create([
            'cat_id' => 2,
            'item_name' => 'Salad',
            'item_chinese_name' => '沙拉',
            'is_allday' => 0,
        ]);

        $results = $this->itemDetails->findOrderReportSummaries([$second->id, $first->id]);

        $this->assertCount(2, $results);
        $this->assertSame(1, $results->first()->cat_id);
    }

    /** @test */
    public function it_filters_items_by_parent_category_flag()
    {
        $parentCategory = CategoryDetail::create([
            'cat_name' => 'Parent',
            'category_chinese_name' => '父类',
            'type' => 'B',
            'parent_id' => 0,
        ]);

        $childCategory = CategoryDetail::create([
            'cat_name' => 'Child',
            'category_chinese_name' => '子类',
            'type' => 'B',
            'parent_id' => $parentCategory->id,
        ]);

        ItemDetail::create([
            'cat_id' => $parentCategory->id,
            'item_name' => 'Parent Item',
            'item_chinese_name' => '父项目',
            'is_allday' => 0,
        ]);

        ItemDetail::create([
            'cat_id' => $childCategory->id,
            'item_name' => 'Child Item',
            'item_chinese_name' => '子项目',
            'is_allday' => 0,
        ]);

        $parentItems = $this->itemDetails->getAll(['is_parent' => true]);
        $childItems = $this->itemDetails->getAll(['is_parent' => false]);

        $this->assertCount(1, $parentItems);
        $this->assertSame('Parent Item', $parentItems->first()->item_name);
        $this->assertCount(1, $childItems);
        $this->assertSame('Child Item', $childItems->first()->item_name);
    }

    /** @test */
    public function it_finds_items_by_ids_and_parent_flag()
    {
        $parentCategory = CategoryDetail::create([
            'cat_name' => 'Main',
            'category_chinese_name' => '主类',
            'type' => 'B',
            'parent_id' => 0,
        ]);

        $childCategory = CategoryDetail::create([
            'cat_name' => 'Sub',
            'category_chinese_name' => '子类',
            'type' => 'B',
            'parent_id' => $parentCategory->id,
        ]);

        $parentItem = ItemDetail::create([
            'cat_id' => $parentCategory->id,
            'item_name' => 'Parent Item',
            'item_chinese_name' => '父项目',
            'is_allday' => 0,
        ]);

        $childItem = ItemDetail::create([
            'cat_id' => $childCategory->id,
            'item_name' => 'Child Item',
            'item_chinese_name' => '子项目',
            'is_allday' => 0,
        ]);

        $parentResults = $this->itemDetails
            ->findByIdsAndParentFlag([$parentItem->id, $childItem->id], true);
        $childResults = $this->itemDetails
            ->findByIdsAndParentFlag([$parentItem->id, $childItem->id], false);

        $this->assertCount(1, $parentResults);
        $this->assertSame($parentItem->id, $parentResults->first()->id);
        $this->assertCount(1, $childResults);
        $this->assertSame($childItem->id, $childResults->first()->id);
    }

    /** @test */
    public function it_filters_item_options_by_category_id()
    {
        ItemOption::create([
            'option_name' => 'Extra Sauce',
            'option_name_cn' => '额外酱',
            'is_paid_item' => 0,
        ]);

    $results = $this->itemOptions->getAll();

        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_creates_and_lists_item_preferences()
    {
        $this->itemPreferences->create([
            'pname' => 'No Salt',
            'pname_cn' => '无盐',
        ]);

        $results = $this->itemPreferences->getAll();

        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_finds_latest_menu_detail_date()
    {
        $this->menuDetails->create([
            'date' => now()->subDay()->toDateString(),
            'items' => ['1', '2'],
            'is_allday' => false,
        ]);

        $latest = $this->menuDetails->create([
            'date' => now()->toDateString(),
            'items' => ['3'],
            'is_allday' => false,
        ]);

        $latestDate = $this->menuDetails->findLatestDate();

        $this->assertSame($latest->date->toDateString(), date('Y-m-d', strtotime($latestDate)));
        $this->assertNotNull($this->menuDetails->findByDate($latest->date->toDateString()));
    }
}
