<?php

namespace Tests\Feature\Admin;

use App\Models\RoomDetail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\DiningApp\DiningAppTestCase;

/**
 * Feature tests for the admin room delete endpoints:
 *   DELETE /api/admin/rooms/{id}
 *   DELETE /api/admin/rooms/bulk-delete
 *
 * Both routes require JWT authentication via the auth:api guard.
 */
class RoomDetailAdminTest extends DiningAppTestCase
{
    // -----------------------------------------------------------------------
    // DELETE /api/admin/rooms/{id}
    // -----------------------------------------------------------------------

    #[Test]
    public function destroy_soft_deletes_a_room(): void
    {
        $admin = $this->makeAdminUser();
        $room  = $this->makeRoom(['room_name' => '206']);

        $this->actingAs($admin, 'api')
            ->deleteJson("/api/admin/rooms/{$room->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('room_details', ['id' => $room->id]);
    }

    #[Test]
    public function destroy_returns_404_for_unknown_room(): void
    {
        $admin = $this->makeAdminUser();

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/admin/rooms/99999')
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $room = $this->makeRoom();

        $this->deleteJson("/api/admin/rooms/{$room->id}")
            ->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // DELETE /api/admin/rooms/bulk-delete
    // -----------------------------------------------------------------------

    #[Test]
    public function bulk_destroy_soft_deletes_multiple_rooms(): void
    {
        $admin  = $this->makeAdminUser();
        $roomA  = $this->makeRoom(['room_name' => '101']);
        $roomB  = $this->makeRoom(['room_name' => '102']);

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/admin/rooms/bulk-delete', ['ids' => [$roomA->id, $roomB->id]])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('room_details', ['id' => $roomA->id]);
        $this->assertSoftDeleted('room_details', ['id' => $roomB->id]);
    }

    #[Test]
    public function bulk_destroy_returns_422_for_missing_ids(): void
    {
        $admin = $this->makeAdminUser();

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/admin/rooms/bulk-delete', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function bulk_destroy_returns_422_for_nonexistent_ids(): void
    {
        $admin = $this->makeAdminUser();

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/admin/rooms/bulk-delete', ['ids' => [99998, 99999]])
            ->assertStatus(422);
    }

    #[Test]
    public function bulk_destroy_requires_authentication(): void
    {
        $room = $this->makeRoom();

        $this->deleteJson('/api/admin/rooms/bulk-delete', ['ids' => [$room->id]])
            ->assertStatus(401);
    }
}
