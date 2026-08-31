<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_successfully(): void
    {
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_movies_index_renders_successfully(): void
    {
        $response = $this->get(route('movies.index'));
        $response->assertStatus(200);
    }
}
