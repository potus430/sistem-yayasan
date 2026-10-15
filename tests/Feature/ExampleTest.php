<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_unauthenticated_visitors_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_custom_login_screen_is_available(): void
    {
        $this->withoutVite()
            ->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke sistem');
    }
}
