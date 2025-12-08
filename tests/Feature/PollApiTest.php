<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createPoll(array $attributes = []): Poll
    {
        return Poll::create(array_merge([
            'question' => 'Test question?',
            'description' => 'Test description',
            'options' => ['Option 1', 'Option 2'],
            'closes_at' => now()->addDays(7),
        ], $attributes));
    }

    public function test_can_list_all_polls(): void
    {
        $this->createPoll(['question' => 'First poll?']);
        $this->createPoll(['question' => 'Second poll?']);
        $this->createPoll(['question' => 'Third poll?']);

        $response = $this->getJson('/api/polls');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_list_polls_does_not_include_soft_deleted(): void
    {
        $poll1 = $this->createPoll(['question' => 'Active poll?']);
        $poll2 = $this->createPoll(['question' => 'Deleted poll?']);
        $poll2->delete();

        $response = $this->getJson('/api/polls');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['question' => 'Active poll?'])
            ->assertJsonMissing(['question' => 'Deleted poll?']);
    }

    public function test_authenticated_user_can_create_poll(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/polls', [
                'question' => 'New poll question?',
                'description' => 'New poll description',
                'options' => ['Yes', 'No', 'Maybe'],
                'closes_at' => now()->addDays(5)->toDateTimeString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'question' => 'New poll question?',
            ]);

        $this->assertDatabaseHas('polls', [
            'question' => 'New poll question?',
        ]);
    }

    public function test_guest_cannot_create_poll(): void
    {
        $response = $this->postJson('/api/polls', [
            'question' => 'New poll?',
            'options' => ['Yes', 'No'],
        ]);

        $response->assertStatus(401);
    }

    public function test_poll_creation_requires_at_least_two_options(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/polls', [
                'question' => 'Invalid poll?',
                'options' => ['Only one option'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('options');
    }

    public function test_poll_creation_requires_question(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/polls', [
                'options' => ['Yes', 'No'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_can_view_single_poll(): void
    {
        $poll = $this->createPoll(['question' => 'Specific poll?']);

        $response = $this->getJson("/api/polls/{$poll->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'question' => 'Specific poll?',
            ]);
    }

    public function test_can_view_poll_results(): void
    {
        $poll = $this->createPoll(['options' => ['Option A', 'Option B', 'Option C']]);
        
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Vote::create(['user_id' => $user1->id, 'poll_id' => $poll->id, 'selected_option' => 'Option A']);
        Vote::create(['user_id' => $user2->id, 'poll_id' => $poll->id, 'selected_option' => 'Option A']);
        Vote::create(['user_id' => $user3->id, 'poll_id' => $poll->id, 'selected_option' => 'Option B']);

        $response = $this->getJson("/api/polls/{$poll->id}/results");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'poll',
                'counts',
                'total',
            ])
            ->assertJson([
                'counts' => [
                    'Option A' => 2,
                    'Option B' => 1,
                    'Option C' => 0,
                ],
                'total' => 3,
            ]);
    }

    public function test_polls_are_ordered_by_newest_first(): void
    {
        $old = $this->createPoll(['question' => 'Old poll?']);
        sleep(1);
        $new = $this->createPoll(['question' => 'New poll?']);

        $response = $this->getJson('/api/polls');

        $polls = $response->json();
        $this->assertEquals('New poll?', $polls[0]['question']);
        $this->assertEquals('Old poll?', $polls[1]['question']);
    }

    public function test_empty_options_are_filtered_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/polls', [
                'question' => 'Filter test?',
                'options' => ['Valid 1', '', '   ', 'Valid 2', null],
            ]);

        $response->assertStatus(201);

        $poll = Poll::latest()->first();
        $this->assertEquals(['Valid 1', 'Valid 2'], $poll->options);
    }

    public function test_poll_closes_at_is_nullable(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/polls', [
                'question' => 'No deadline poll?',
                'options' => ['Yes', 'No'],
            ]);

        $response->assertStatus(201);

        $poll = Poll::latest()->first();
        $this->assertNull($poll->closes_at);
    }

    public function test_cannot_view_soft_deleted_poll(): void
    {
        $poll = $this->createPoll();
        $poll->delete();

        $response = $this->getJson("/api/polls/{$poll->id}");

        $response->assertStatus(404);
    }
}
