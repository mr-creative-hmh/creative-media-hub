<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_to_movies(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('movies.index'));
    }

    public function test_movies_index_renders_successfully(): void
    {
        $response = $this->get(route('movies.index'));
        $response->assertStatus(200);
    }
}
