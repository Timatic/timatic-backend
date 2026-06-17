<?php

namespace Tests\Feature\Filters;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user for authentication
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('entries.read');
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_filters_entries_with_exact_match_on_user_full_name()
    {
        Entry::factory()->create(['user_full_name' => 'John Doe']);
        Entry::factory()->create(['user_full_name' => 'Jane Smith']);

        $response = $this->getJson('/entries?filter[userFullName]=John Doe');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_filters_entries_with_contains_operator_on_user_full_name()
    {
        Entry::factory()->create(['user_full_name' => 'John Doe']);
        Entry::factory()->create(['user_full_name' => 'Jane Doe']);
        Entry::factory()->create(['user_full_name' => 'Bob Smith']);

        $response = $this->getJson('/entries?filter[userFullName][contains]=Doe');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_filters_entries_with_exact_match_on_ticket_number()
    {
        Entry::factory()->create(['ticket_number' => 'TICKET-123']);
        Entry::factory()->create(['ticket_number' => 'TICKET-456']);

        $response = $this->getJson('/entries?filter[ticketNumber]=TICKET-123');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_filters_entries_with_contains_operator_on_ticket_number()
    {
        Entry::factory()->create(['ticket_number' => 'TICKET-123']);
        Entry::factory()->create(['ticket_number' => 'TICKET-456']);
        Entry::factory()->create(['ticket_number' => 'OTHER-789']);

        $response = $this->getJson('/entries?filter[ticketNumber][contains]=TICKET');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_is_case_insensitive_for_contains_operator()
    {
        Entry::factory()->create(['user_full_name' => 'John Doe']);
        Entry::factory()->create(['user_full_name' => 'jane smith']);

        $response = $this->getJson('/entries?filter[userFullName][contains]=john');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data'); // MySQL LIKE is case-insensitive by default
    }
}
