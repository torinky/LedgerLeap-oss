<?php

namespace Tests\Unit\Config;

use App\Helpers\Version;
use Tests\TestCase;

class VersionResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (file_exists(base_path('.version'))) {
            unlink(base_path('.version'));
        }
    }

    protected function tearDown(): void
    {
        putenv('APP_VERSION');

        if (file_exists(base_path('.version'))) {
            unlink(base_path('.version'));
        }

        parent::tearDown();
    }

    public function test_config_key_exists_and_is_string()
    {
        $version = config('ledgerleap.version');

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    public function test_returns_app_version_env_when_set()
    {
        putenv('APP_VERSION=v2026.3.0');

        $version = Version::resolve();

        $this->assertSame('v2026.3.0', $version);
    }

    public function test_app_version_env_overrides_version_file()
    {
        file_put_contents(base_path('.version'), 'v2025.1.0');
        putenv('APP_VERSION=v2026.3.0');

        $version = Version::resolve();

        $this->assertSame('v2026.3.0', $version, 'APP_VERSION env should take priority over .version file');
    }

    public function test_returns_version_file_when_env_not_set()
    {
        putenv('APP_VERSION');

        file_put_contents(base_path('.version'), 'v2025.1.0');

        $version = Version::resolve();

        $this->assertSame('v2025.1.0', $version);
    }

    public function test_trims_whitespace_from_version_file()
    {
        putenv('APP_VERSION');

        file_put_contents(base_path('.version'), "  v2025.2.0\n");

        $version = Version::resolve();

        $this->assertSame('v2025.2.0', $version);
    }

    public function test_ignores_empty_version_file()
    {
        putenv('APP_VERSION');

        file_put_contents(base_path('.version'), "   \n");

        $version = Version::resolve();

        $this->assertNotEmpty($version);
        $this->assertNotSame('', $version);
    }

    public function test_returns_non_empty_string_when_no_sources_available()
    {
        putenv('APP_VERSION');

        $version = Version::resolve();

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }
}
