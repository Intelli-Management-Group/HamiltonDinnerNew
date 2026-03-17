<?php

namespace Tests\Feature\DiningApp;

use App\Models\RoomDetail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for:
 *   GET  /api/{room_id}/get-room-details
 *   POST /api/{room_id}/update-room-details
 *
 * Both routes are inside the APIToken middleware group and require authentication.
 * The controller delegates directly to DiningAppService without additional role checks.
 */
class RoomDetailsTest extends DiningAppTestCase
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
    // get-room-details
    // -----------------------------------------------------------------------

    #[Test]
    public function get_room_details_returns_room_data(): void
    {
        $room = $this->makeRoom([
            'room_name'     => '205',
            'occupancy'     => 4,
            'resident_name' => 'John Smith',
        ]);

        $data = $this->withHeaders($this->authHeaders)
            ->getJson("/api/{$room->id}/get-room-details")
            ->assertStatus(200)
            ->assertJson([
                'ResponseCode' => '1',
                'ResponseText' => 'Room Details Found',
            ])
            ->json();

        $this->assertSame('205',        $data['Data']['room_name']);
        $this->assertSame(4,            $data['Data']['occupancy']);
        $this->assertSame('John Smith', $data['Data']['resident_name']);
    }

    #[Test]
    public function get_room_details_returns_error_for_unknown_room(): void
    {
        $this->withHeaders($this->authHeaders)
            ->getJson('/api/9999/get-room-details')
            ->assertStatus(200)
            ->assertJson([
                'ResponseCode' => '0',
                'ResponseText' => 'Room Not Found',
            ]);
    }

    #[Test]
    public function get_room_details_requires_authentication(): void
    {
        $room = $this->makeRoom();
        $this->getJson("/api/{$room->id}/get-room-details")
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }

    // -----------------------------------------------------------------------
    // update-room-details
    // -----------------------------------------------------------------------

    #[Test]
    public function update_room_details_persists_changes(): void
    {
        $room = $this->makeRoom(['room_name' => '101', 'occupancy' => 2]);

        $data = $this->withHeaders($this->authHeaders)
            ->postJson("/api/{$room->id}/update-room-details", [
                'occupancy'     => 5,
                'resident_name' => 'Updated Name',
            ])
            ->assertStatus(200)
            ->assertJson([
                'ResponseCode' => '1',
                'ResponseText' => 'Room Details Updated Successfully',
            ])
            ->json();

        $this->assertSame(5,              $data['Data']['occupancy']);
        $this->assertSame('Updated Name', $data['Data']['resident_name']);

        // Verify persisted to DB
        $this->assertDatabaseHas('room_details', [
            'id'            => $room->id,
            'occupancy'     => 5,
            'resident_name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function update_room_details_returns_error_for_unknown_room(): void
    {
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/9999/update-room-details', ['occupancy' => 3])
            ->assertStatus(200)
            ->assertJson([
                'ResponseCode' => '0',
                'ResponseText' => 'Room Not Found',
            ]);
    }

    #[Test]
    public function update_room_details_requires_authentication(): void
    {
        $room = $this->makeRoom();
        $this->postJson("/api/{$room->id}/update-room-details", ['occupancy' => 3])
            ->assertStatus(200)
            ->assertJson(['ResponseCode' => '11']);
    }
}
