<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->isBrowserTest()) {
            $this->withoutVite();
        }
    }

    /**
     * Browser tests drive a real browser and need the built Vite assets, so the
     * Vite manifest must not be faked away for them.
     */
    private function isBrowserTest(): bool
    {
        return str_contains(static::class, '\\Browser\\');
    }
}
