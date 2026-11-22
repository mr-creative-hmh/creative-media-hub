<?php

namespace Tests;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Organizer\PhysicalOrganizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Whether this test case should automatically authenticate as admin in setUp().
     */
    protected bool $authenticateAsAdmin = true;

    protected function setUp(): void
    {
        parent::setUp();
        PhysicalOrganizerService::resetStaticCaches();
        AppSetting::flushMemo();

        if ($this->authenticateAsAdmin && in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->actingAsAdmin();
        }
    }

    /**
     * Authenticate as an administrator profile for protected test routes.
     */
    public function actingAsAdmin(?User $user = null): static
    {
        $admin = $user ?? User::where('role', 'admin')->first() ?? User::factory()->admin()->create();

        return $this->actingAs($admin);
    }

    /**
     * Authenticate as a standard viewer profile for protected test routes.
     */
    public function actingAsUser(?User $user = null): static
    {
        $viewer = $user ?? User::factory()->create();

        return $this->actingAs($viewer);
    }
}
