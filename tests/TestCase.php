<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles/permissions and departments before each test
     * (DatabaseSeeder skips local dev accounts outside the local env).
     */
    protected $seed = true;
}
