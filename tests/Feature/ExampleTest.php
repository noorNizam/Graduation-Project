<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_api_returns_404_for_unknown_route(): void
    {
        $response = $this->getJson('/api/unknown-route');

        $response->assertStatus(404);
    }
}
