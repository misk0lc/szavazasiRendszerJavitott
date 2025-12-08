<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPollTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    protected function createRegularUser(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    protected function createPoll(): Poll
    {
        return Poll::create([
            'question' => 'Test question?',
            'description' => 'Test description',
            'options' => ['Option 1', 'Option 2'],
            'closes_at' => now()->addDays(7),
        ]);
    }

    public function test_admin_can_update_poll(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/polls/{$poll->id}", [
                'question' => 'Updated question?',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Poll updated successfully',
            ]);

        $this->assertDatabaseHas('polls', [
            'id' => $poll->id,
            'question' => 'Updated question?',
            'description' => 'Updated description',
        ]);
    }

    public function test_non_admin_cannot_update_poll(): void
    {
        $user = $this->createRegularUser();
        $poll = $this->createPoll();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/polls/{$poll->id}", [
                'question' => 'Updated question?',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized. Admin access required.',
            ]);
    }

    public function test_admin_can_soft_delete_poll(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/polls/{$poll->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Poll soft deleted successfully',
            ]);

        $this->assertSoftDeleted('polls', ['id' => $poll->id]);
    }

    public function test_admin_can_restore_soft_deleted_poll(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();
        $poll->delete(); // Soft delete

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/polls/{$poll->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Poll restored successfully',
            ]);

        $this->assertDatabaseHas('polls', [
            'id' => $poll->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_cannot_restore_non_deleted_poll(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/polls/{$poll->id}/restore");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Poll is not deleted',
            ]);
    }

    public function test_admin_can_force_delete_poll(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();
        $poll->delete(); // Soft delete first

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/polls/{$poll->id}/force");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Poll permanently deleted',
            ]);

        $this->assertDatabaseMissing('polls', ['id' => $poll->id]);
    }

    public function test_admin_can_list_trashed_polls(): void
    {
        $admin = $this->createAdmin();
        $poll1 = $this->createPoll();
        $poll2 = Poll::create([
            'question' => 'Another question?',
            'options' => ['Yes', 'No'],
        ]);
        
        $poll1->delete();
        $poll2->delete();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/polls/trashed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'deleted_polls' => [
                    '*' => ['id', 'question', 'deleted_at']
                ]
            ]);

        $this->assertCount(2, $response->json('deleted_polls'));
    }

    public function test_admin_can_close_poll_immediately(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/polls/{$poll->id}/close");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Poll closed successfully',
            ]);

        $poll->refresh();
        $this->assertNotNull($poll->closes_at);
        $this->assertTrue($poll->closes_at->isPast() || $poll->closes_at->isToday());
    }

    public function test_admin_can_extend_poll_deadline(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();
        $newDate = now()->addDays(30);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/polls/{$poll->id}/extend", [
                'closes_at' => $newDate->toDateTimeString(),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Poll deadline extended successfully',
            ]);

        $this->assertDatabaseHas('polls', [
            'id' => $poll->id,
        ]);

        $poll->refresh();
        $this->assertEquals($newDate->format('Y-m-d H:i:s'), $poll->closes_at->format('Y-m-d H:i:s'));
    }

    public function test_admin_cannot_extend_poll_with_past_date(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();
        $pastDate = now()->subDays(1);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/polls/{$poll->id}/extend", [
                'closes_at' => $pastDate->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('closes_at');
    }

    public function test_admin_can_open_poll_indefinitely(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/polls/{$poll->id}/open");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Poll opened (no closing date)',
            ]);

        $this->assertDatabaseHas('polls', [
            'id' => $poll->id,
            'closes_at' => null,
        ]);
    }

    public function test_guest_cannot_access_admin_endpoints(): void
    {
        $poll = $this->createPoll();

        $response = $this->putJson("/api/admin/polls/{$poll->id}", [
            'question' => 'Hacked?',
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_update_poll_options(): void
    {
        $admin = $this->createAdmin();
        $poll = $this->createPoll();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/polls/{$poll->id}", [
                'options' => ['New Option 1', 'New Option 2', 'New Option 3'],
            ]);

        $response->assertStatus(200);

        $poll->refresh();
        $this->assertEquals(['New Option 1', 'New Option 2', 'New Option 3'], $poll->options);
    }
}
