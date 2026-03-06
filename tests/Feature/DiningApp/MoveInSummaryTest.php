<?php

namespace Tests\Feature\DiningApp;

use App\Models\RoomDetail;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for GET /api/get-move-in-summary-values.
 *
 * Returns all move_in_summary_values rows as a flat key → value map.
 * Route is inside the APIToken middleware group; authentication is required.
 */
class MoveInSummaryTest extends DiningAppTestCase
{
    private RoomDetail $authRoom;
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authRoom    = $this->makeRoom(['room_name' => 'auth']);
        $this->authHeaders = $this->residentHeaders($this->authRoom);
    }

    private function insertSummaryValue(string $key, ?string $value): void
    {
        DB::table('move_in_summary_values')->insert([
            'key_param' => $key,
            'value'     => $value,
        ]);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function returns_key_value_pairs_from_table(): void
    {
        $this->insertSummaryValue('room_type', 'Standard');
        $this->insertSummaryValue('meal_plan', 'Full Board');
        $this->insertSummaryValue('floor',     '3rd');

        $data = $this->withHeaders($this->authHeaders)
            ->getJson('/api/get-move-in-summary-values')
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1'])
            ->json();

        $this->assertSame('Standard',   $data['Data']['room_type']);
        $this->assertSame('Full Board', $data['Data']['meal_plan']);
        $this->assertSame('3rd',        $data['Data']['floor']);
    }

    #[Test]
    public function returns_empty_data_when_table_is_empty(): void
    {
        $data = $this->withHeaders($this->authHeaders)
            ->getJson('/api/get-move-in-summary-values')
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '1'])
            ->json();

        $this->assertEmpty($data['Data']);
    }

    #[Test]
    public function null_values_are_included_in_response(): void
    {
        $this->insertSummaryValue('optional_field', null);

        $data = $this->withHeaders($this->authHeaders)
            ->getJson('/api/get-move-in-summary-values')
            ->assertStatus(200)
            ->json();

        $this->assertArrayHasKey('optional_field', $data['Data']);
        $this->assertNull($data['Data']['optional_field']);
    }

    #[Test]
    public function response_is_keyed_by_key_param_not_id(): void
    {
        $this->insertSummaryValue('my_key', 'my_value');

        $data = $this->withHeaders($this->authHeaders)
            ->getJson('/api/get-move-in-summary-values')
            ->assertStatus(200)
            ->json();

        // Data should be a flat associative map, not an indexed array
        $this->assertIsArray($data['Data']);
        $this->assertArrayHasKey('my_key', $data['Data']);
        $this->assertSame('my_value', $data['Data']['my_key']);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->getJson('/api/get-move-in-summary-values')
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }
}
