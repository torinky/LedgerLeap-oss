<?php

namespace App\Helpers;

class Version
{
    /**
     * Resolve the application version with the following priority:
     * 1. APP_VERSION environment variable
     * 2. .version file at project root
     * 3. git describe --tags (latest tag)
     * 4. '0.0.0' fallback
     */
    public static function resolve(): string
    {
        $version = env('APP_VERSION');

        if ($version) {
            return $version;
        }

        $versionFile = base_path('.version');
        if (file_exists($versionFile)) {
            $content = trim(file_get_contents($versionFile));
            if ($content !== '') {
                return $content;
            }
        }

        $gitDescribe = trim(shell_exec('git -C '.base_path().' describe --tags --abbrev=0 2>/dev/null') ?? '');
        if ($gitDescribe !== '') {
            return $gitDescribe;
        }

        return '0.0.0';
    }
}
