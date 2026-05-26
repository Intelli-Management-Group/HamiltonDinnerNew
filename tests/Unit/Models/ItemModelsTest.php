<?php

namespace Tests\Unit\Models;

use App\Models\ItemDetail;
use App\Models\ItemOption;
use App\Models\ItemPreference;
use App\Models\MenuDetail;
use App\Repositories\Eloquent\ItemDetailRepository;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ItemModelsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function item_detail_filters_by_category_id_with_repository()
    {
        ItemDetail::create([
            'cat_id' => 1,
            'item_name' => 'Item A',
            'item_chinese_name' => '项目A',
            'is_allday' => 0,
        ]);

        ItemDetail::create([
            'cat_id' => 2,
            'item_name' => 'Item B',
            'item_chinese_name' => '项目B',
            'is_allday' => 0,
        ]);

        $repository = new ItemDetailRepository(new ItemDetail());
        $items = $repository->getAll(['cat_id' => 1]);

        $this->assertCount(1, $items);
        $this->assertSame('Item A', $items->first()->item_name);
    }

    #[Test]
    public function item_detail_relations_are_configured()
    {
        $item = new ItemDetail();

    $this->assertInstanceOf(BelongsTo::class, $item->category());
    }

    #[Test]
    public function item_option_casts_is_paid_item_to_boolean()
    {
        $option = ItemOption::create([
            'option_name' => 'Upgrade',
            'option_name_cn' => '升级',
            'is_paid_item' => 1,
        ]);

        $this->assertIsBool($option->is_paid_item);
        $this->assertTrue($option->is_paid_item);
    }

    #[Test]
    public function item_option_relations_are_configured()
    {
        $option = new ItemOption();

        $this->assertInstanceOf(HasOne::class, $option->itemData());
        $this->assertInstanceOf(BelongsTo::class, $option->options());
        $this->assertInstanceOf(BelongsTo::class, $option->preference());
    }

    #[Test]
    public function menu_detail_casts_items_to_array()
    {
        $menu = MenuDetail::create([
            'date' => now()->toDateString(),
            'items' => ['1', '2', '3'],
            'is_allday' => false,
        ]);

        $this->assertIsArray($menu->items);
        $this->assertSame(['1', '2', '3'], $menu->items);
    }

    #[Test]
    public function item_preference_can_be_created()
    {
        $preference = ItemPreference::create([
            'pname' => 'No Salt',
            'pname_cn' => '无盐',
        ]);

        $this->assertDatabaseHas('item_preferences', ['id' => $preference->id]);
    }
}
