<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Root now redirects to the authenticated dashboard (which in turn
        // redirects guests to login), instead of rendering the scaffold page.
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
