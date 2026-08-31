<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_dashboard_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
