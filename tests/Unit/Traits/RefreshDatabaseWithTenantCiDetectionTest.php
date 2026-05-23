<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

final class RefreshDatabaseWithTenantCiDetectionTest extends TestCase
{
    #[Test]
    #[DataProvider('ciEnvironmentProvider')]
    public function it_detects_ci_environment_from_common_flags(array $env, bool $expected): void
    {
        $probe = new class
        {
            use RefreshDatabaseWithTenant;

            public function detect(): bool
            {
                return $this->isCiEnvironment();
            }
        };

        $this->withTemporaryEnvironment($env, function () use ($probe, $expected): void {
            self::assertSame($expected, $probe->detect());
        });
    }

    public static function ciEnvironmentProvider(): array
    {
        return [
            'CI true' => [['CI' => 'true'], true],
            'GITHUB_ACTIONS true' => [['GITHUB_ACTIONS' => 'true'], true],
            'neither set' => [[], false],
        ];
    }

    /**
     * @param  array<string, string>  $env
     */
    private function withTemporaryEnvironment(array $env, callable $callback): void
    {
        $keys = ['CI', 'GITHUB_ACTIONS'];
        $backup = [];

        foreach ($keys as $key) {
            $backup[$key] = [
                'server' => array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
                'env' => array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
                'getenv' => getenv($key),
            ];

            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        }

        try {
            foreach ($env as $key => $value) {
                $_SERVER[$key] = $value;
                $_ENV[$key] = $value;
                putenv($key.'='.$value);
            }

            $callback();
        } finally {
            foreach ($keys as $key) {
                $server = $backup[$key]['server'];
                $envValue = $backup[$key]['env'];
                $getenvValue = $backup[$key]['getenv'];

                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }

                if ($envValue === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $envValue;
                }

                if ($getenvValue === false || $getenvValue === null) {
                    putenv($key);
                } else {
                    putenv($key.'='.$getenvValue);
                }
            }
        }
    }
}
