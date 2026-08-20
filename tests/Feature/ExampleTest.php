<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guests_are_redirected_to_login_through_vehicles(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/vehicles');
        $this->get('/vehicles')->assertRedirect('/login');
    }
}
