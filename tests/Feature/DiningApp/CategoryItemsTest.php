<?php

namespace Tests\Feature\DiningApp;

use App\Models\RoomDetail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for POST /api/get-category-specific-items.
 *
 * Returns items belonging to a given category, classified as 'item' (top-level)
 * or 'sub_cat_item' (child of a sub-category, i.e. parent_id != 0).
 * Route is inside the APIToken middleware group; authentication is required.
 */
class CategoryItemsTest extends DiningAppTestCase
{
    private RoomDetail $authRoom;
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authRoom    = $this->makeRoom(['room_name' => 'auth']);
        $this->authHeaders = $this->residentHeaders($this->authRoom);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function returns_items_for_the_given_category(): void
    {
        $cat  = $this->makeCategory(['cat_name' => 'Breakfast', 'type' => 1]);
        $item = $this->makeItem($cat, ['item_name' => 'French Toast']);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/get-category-specific-items', ['categoryId' => $cat->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1', 'ResponseText' => 'Items Found'])
            ->json();

        $this->assertCount(1, $data['Data']);
        $this->assertSame('French Toast', $data['Data'][0]['item_name']);
        $this->assertSame($item->id,      $data['Data'][0]['item_id']);
    }

    #[Test]
    public function top_level_item_has_type_item(): void
    {
        // parent_id = 0 on the category → type 'item'
        $cat = $this->makeCategory(['parent_id' => 0]);
        $this->makeItem($cat);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/get-category-specific-items', ['categoryId' => $cat->id])
            ->assertStatus(200)
            ->json();

        $this->assertSame('item', $data['Data'][0]['type']);
    }

    #[Test]
    public function sub_category_item_has_type_sub_cat_item(): void
    {
        // Create a parent category, then a child category under it (parent_id != 0)
        $parent = $this->makeCategory(['cat_name' => 'Entrees', 'parent_id' => 0]);
        $child  = $this->makeCategory(['cat_name' => 'Mains',   'parent_id' => $parent->id]);
        $this->makeItem($child, ['item_name' => 'Grilled Chicken']);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/get-category-specific-items', ['categoryId' => $child->id])
            ->assertStatus(200)
            ->json();

        $this->assertSame('sub_cat_item', $data['Data'][0]['type']);
    }

    #[Test]
    public function returns_empty_data_array_when_no_items_in_category(): void
    {
        $cat = $this->makeCategory();

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/get-category-specific-items', ['categoryId' => $cat->id])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1'])
            ->json();

        $this->assertEmpty($data['Data']);
    }

    #[Test]
    public function does_not_return_soft_deleted_items(): void
    {
        $cat  = $this->makeCategory();
        $item = $this->makeItem($cat, ['item_name' => 'Deleted Item']);
        $item->delete(); // soft delete

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/get-category-specific-items', ['categoryId' => $cat->id])
            ->assertStatus(200)
            ->json();

        $this->assertEmpty($data['Data']);
    }

    #[Test]
    public function returns_multiple_items_for_a_category(): void
    {
        $cat = $this->makeCategory();
        $this->makeItem($cat, ['item_name' => 'Item A']);
        $this->makeItem($cat, ['item_name' => 'Item B']);
        $this->makeItem($cat, ['item_name' => 'Item C']);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/get-category-specific-items', ['categoryId' => $cat->id])
            ->assertStatus(200)
            ->json();

        $this->assertCount(3, $data['Data']);
        $names = array_column($data['Data'], 'item_name');
        $this->assertContains('Item A', $names);
        $this->assertContains('Item B', $names);
        $this->assertContains('Item C', $names);
    }

    #[Test]
    public function does_not_return_items_from_other_categories(): void
    {
        $catA = $this->makeCategory(['cat_name' => 'Cat A']);
        $catB = $this->makeCategory(['cat_name' => 'Cat B']);
        $this->makeItem($catA, ['item_name' => 'Item in A']);
        $this->makeItem($catB, ['item_name' => 'Item in B']);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/get-category-specific-items', ['categoryId' => $catA->id])
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $data['Data']);
        $this->assertSame('Item in A', $data['Data'][0]['item_name']);
    }

    #[Test]
    public function each_item_has_expected_response_keys(): void
    {
        $cat = $this->makeCategory();
        $this->makeItem($cat);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson('/api/get-category-specific-items', ['categoryId' => $cat->id])
            ->assertStatus(200)
            ->json();

        $item = $data['Data'][0];
        foreach (['type', 'parent_id', 'item_name', 'item_id', 'preference', 'options', 'chinese_name', 'comment', 'qty', 'order_id'] as $key) {
            $this->assertArrayHasKey($key, $item, "Missing key: $key");
        }
        // Defaults for a freshly returned item (not yet ordered)
        $this->assertSame(0, $item['qty']);
        $this->assertSame(0, $item['order_id']);
        $this->assertSame('', $item['comment']);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->postJson('/api/get-category-specific-items', ['categoryId' => 1])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }
}
