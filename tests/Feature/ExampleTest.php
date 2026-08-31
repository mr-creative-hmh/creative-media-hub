<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_movies(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('movies.index'));
    }
}
