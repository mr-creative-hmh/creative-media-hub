<?php

namespace Tests;

use App\Models\AppSetting;
use App\Services\Organizer\PhysicalOrganizerService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PhysicalOrganizerService::resetStaticCaches();
        AppSetting::flushMemo();
    }
}
