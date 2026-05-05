<?php

namespace App\Services;

class PerformanceLogBuffer
{
    private static array $buffer = [];

    public static function push(array $data): void
    {
        self::$buffer[] = $data;
    }

    public static function flush(): void
    {
        if (empty(self::$buffer)) {
            return;
        }

        $statsFile = storage_path('logs/performance_stats.json');
        $stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];
        if (! is_array($stats)) {
            $stats = [];
        }

        foreach (self::$buffer as $data) {
            $stats[] = $data;
        }

        file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
        self::$buffer = [];
    }

    public static function clear(): void
    {
        self::$buffer = [];
    }
}
