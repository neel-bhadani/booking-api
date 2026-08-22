<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_rooms(): void
    {
        // Arrange — a user, and some rooms to list
        $member = User::factory()->create();
        Room::factory()->count(3)->create();

        // Act + Assert
        $this->actingAs($member)
            ->getJson('/api/rooms')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_member_can_view_a_room()
    {
        $member = User::factory()->create();
        $room = Room::factory()->create();
        $this->actingAs($member)->getJson("/api/rooms/{$room->id}")->assertStatus(200);
    }

    public function test_member_cannot_create_room(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->postJson('/api/rooms', [
                'name' => 'Sakura',
                'location' => 'Floor 2',
                'capacity' => 8,
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_create_room(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/rooms', [
                'name' => 'Sakura',
                'location' => 'Floor 2',
                'capacity' => 8,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('rooms', ['name' => 'Sakura']);
    }

    public function test_create_room_fails_without_name(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->postJson('/api/rooms', [
                // 'name' => 'Sakura',
                'location' => 'Floor 2',
                'capacity' => 8,
            ])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_member_cannot_update_room(): void
    {
        $member = User::factory()->create();
        $room = Room::factory()->create();

        $this->actingAs($member)
            ->patchJson("/api/rooms/{$room->id}", ['capacity' => 20])
            ->assertStatus(403);
    }

    public function test_admin_can_update_room(): void
    {
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create(['capacity' => 5]);

        $this->actingAs($admin)
            ->patchJson("/api/rooms/{$room->id}", ['capacity' => 20])
            ->assertStatus(200);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'capacity' => 20,
        ]);
    }

    public function test_member_cannot_delete_room(): void
    {
        $member = User::factory()->create();
        $room = Room::factory()->create();

        $this->actingAs($member)
            ->deleteJson("/api/rooms/{$room->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_guest_cannot_access_rooms(): void
    {
        $this->getJson('/api/rooms')
            ->assertStatus(401);
    }
}
