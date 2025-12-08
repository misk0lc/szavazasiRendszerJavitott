<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createPoll(array $attributes = []): Poll
    {
        return Poll::create(array_merge([
            'question' => 'Test question?',
            'options' => ['Option A', 'Option B', 'Option C'],
            'closes_at' => now()->addDays(7),
        ], $attributes));
    }

    public function test_authenticated_user_can_vote(): void
    {
        $user = User::factory()->create();
        $poll = $this->createPoll();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", [
                'selected_option' => 'Option A',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Vote recorded successfully',
            ]);

        $this->assertDatabaseHas('votes', [
            'user_id' => $user->id,
            'poll_id' => $poll->id,
            'selected_option' => 'Option A',
        ]);
    }

    public function test_guest_cannot_vote(): void
    {
        $poll = $this->createPoll();

        $response = $this->postJson("/api/polls/{$poll->id}/vote", [
            'selected_option' => 'Option A',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_cannot_vote_twice_on_same_poll(): void
    {
        $user = User::factory()->create();
        $poll = $this->createPoll();

        // First vote
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", [
                'selected_option' => 'Option A',
            ])
            ->assertStatus(201);

        // Second vote attempt
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", [
                'selected_option' => 'Option B',
            ]);

        $response->assertStatus(422);

        // Verify only one vote exists
        $this->assertDatabaseCount('votes', 1);
        $this->assertDatabaseHas('votes', [
            'user_id' => $user->id,
            'poll_id' => $poll->id,
            'selected_option' => 'Option A',
        ]);
    }

    public function test_cannot_vote_with_invalid_option(): void
    {
        $user = User::factory()->create();
        $poll = $this->createPoll(['options' => ['Yes', 'No']]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", [
                'selected_option' => 'Invalid Option',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_option');

        $this->assertDatabaseCount('votes', 0);
    }

    public function test_cannot_vote_on_closed_poll(): void
    {
        $user = User::factory()->create();
        $poll = $this->createPoll([
            'closes_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", [
                'selected_option' => 'Option A',
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('votes', 0);
    }

    public function test_can_vote_on_poll_without_closing_date(): void
    {
        $user = User::factory()->create();
        $poll = $this->createPoll([
            'closes_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", [
                'selected_option' => 'Option A',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('votes', [
            'user_id' => $user->id,
            'poll_id' => $poll->id,
        ]);
    }

    public function test_vote_requires_selected_option(): void
    {
        $user = User::factory()->create();
        $poll = $this->createPoll();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_option');
    }

    public function test_multiple_users_can_vote_on_same_poll(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $poll = $this->createPoll();

        $this->actingAs($user1, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", ['selected_option' => 'Option A'])
            ->assertStatus(201);

        $this->actingAs($user2, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", ['selected_option' => 'Option B'])
            ->assertStatus(201);

        $this->actingAs($user3, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", ['selected_option' => 'Option A'])
            ->assertStatus(201);

        $this->assertDatabaseCount('votes', 3);
    }

    public function test_cannot_vote_on_soft_deleted_poll(): void
    {
        $user = User::factory()->create();
        $poll = $this->createPoll();
        $poll->delete(); // Soft delete

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", [
                'selected_option' => 'Option A',
            ]);

        $response->assertStatus(404);
    }

    public function test_vote_timestamps_are_recorded(): void
    {
        $user = User::factory()->create();
        $poll = $this->createPoll();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", [
                'selected_option' => 'Option A',
            ]);

        $vote = Vote::where('user_id', $user->id)
            ->where('poll_id', $poll->id)
            ->first();

        $this->assertNotNull($vote->created_at);
        $this->assertNotNull($vote->updated_at);
    }
}
