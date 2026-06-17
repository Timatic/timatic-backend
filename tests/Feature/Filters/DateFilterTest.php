<?php

namespace Tests\Feature\Filters;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateFilterTest extends TestCase
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
    public function it_filters_entries_with_greater_than_or_equal_operator()
    {
        // Create entries with different dates
        Entry::factory()->create(['started_at' => '2025-01-01 10:00:00']);
        Entry::factory()->create(['started_at' => '2025-01-15 10:00:00']);
        Entry::factory()->create(['started_at' => '2025-02-01 10:00:00']);

        // Query with gte operator
        $response = $this->getJson('/entries?filter[startedAt][gte]=2025-01-15T00:00:00Z');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data'); // Should return 2 entries
    }

    /** @test */
    public function it_filters_entries_with_date_range_using_multiple_operators()
    {
        // Create entries spanning multiple months
        Entry::factory()->create(['started_at' => '2024-12-15 10:00:00']);
        Entry::factory()->create(['started_at' => '2025-01-05 10:00:00']);
        Entry::factory()->create(['started_at' => '2025-01-20 10:00:00']);
        Entry::factory()->create(['started_at' => '2025-02-05 10:00:00']);

        // Query with both gte and lte (date range)
        $response = $this->getJson(
            '/entries?filter[startedAt][gte]=2025-01-01T00:00:00Z&filter[startedAt][lte]=2025-01-31T23:59:59Z'
        );

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data'); // Should return only January entries
    }

    /** @test */
    public function it_filters_with_less_than_operator()
    {
        Entry::factory()->create(['started_at' => '2025-01-01 10:00:00']);
        Entry::factory()->create(['started_at' => '2025-01-15 10:00:00']);
        Entry::factory()->create(['started_at' => '2025-02-01 10:00:00']);

        $response = $this->getJson('/entries?filter[startedAt][lt]=2025-01-15T00:00:00Z');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data'); // Should return 1 entry
    }

    /** @test */
    public function it_ignores_unknown_operators()
    {
        Entry::factory()->create(['started_at' => '2025-01-01 10:00:00']);

        // Try an invalid operator - should not crash
        $response = $this->getJson('/entries?filter[startedAt][invalid]=2025-01-01');

        $response->assertStatus(200);
        // The filter should be ignored, returning all entries
    }
}
