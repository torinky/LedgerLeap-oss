<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidAutoLinkPattern;
use Tests\TestCase;

class ValidAutoLinkPatternTest extends TestCase
{
    public function test_it_passes_for_valid_regular_expression(): void
    {
        $rule = new ValidAutoLinkPattern;

        $calledFail = false;
        $rule->validate('pattern', '/^[A-Z]+\-\d+$/', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_fails_for_invalid_regular_expression(): void
    {
        $rule = new ValidAutoLinkPattern;

        $calledFail = false;
        $rule->validate('pattern', '/^[A-Z+(/', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_accepts_simple_patterns(): void
    {
        $rule = new ValidAutoLinkPattern;

        $validPatterns = [
            '/\d+/',
            '/[a-z]+/',
            '/^test$/',
            '/pattern/i',
            '#pattern#',
            '~pattern~',
        ];

        foreach ($validPatterns as $pattern) {
            $calledFail = false;
            $rule->validate('pattern', $pattern, function ($message) use (&$calledFail) {
                $calledFail = true;
            });

            $this->assertFalse($calledFail, "パターン「{$pattern}」は有効であるべき");
        }
    }

    public function test_it_rejects_malformed_patterns(): void
    {
        $rule = new ValidAutoLinkPattern;

        $invalidPatterns = [
            '/[/',           // 閉じ括弧がない
            '/(?/',          // 不完全なグループ
            '/(?P<name)/',   // 不完全な名前付きキャプチャ
            '/(/',           // 開き括弧のみ
        ];

        foreach ($invalidPatterns as $pattern) {
            $calledFail = false;
            $rule->validate('pattern', $pattern, function ($message) use (&$calledFail) {
                $calledFail = true;
            });

            $this->assertTrue($calledFail, "パターン「{$pattern}」は不正であるべき");
        }
    }

    public function test_it_handles_patterns_with_capture_groups(): void
    {
        $rule = new ValidAutoLinkPattern;

        $patternsWithCaptures = [
            '/(\d+)/',
            '/(?P<number>\d+)/',
            '/(\w+)-(\d+)/',
            '/(?P<prefix>\w+)-(?P<num>\d+)/',
        ];

        foreach ($patternsWithCaptures as $pattern) {
            $calledFail = false;
            $rule->validate('pattern', $pattern, function ($message) use (&$calledFail) {
                $calledFail = true;
            });

            $this->assertFalse($calledFail, "パターン「{$pattern}」はキャプチャグループ付きで有効であるべき");
        }
    }

    public function test_it_handles_complex_patterns(): void
    {
        $rule = new ValidAutoLinkPattern;

        $complexPatterns = [
            '/^[A-Z]{2,3}-\d{4,6}(?:-[A-Z])?$/',
            '/(?:DOC|CNT)-\d+/',
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
        ];

        foreach ($complexPatterns as $pattern) {
            $calledFail = false;
            $rule->validate('pattern', $pattern, function ($message) use (&$calledFail) {
                $calledFail = true;
            });

            $this->assertFalse($calledFail, "複雑なパターン「{$pattern}」は有効であるべき");
        }
    }

    public function test_it_handles_empty_string_gracefully(): void
    {
        $rule = new ValidAutoLinkPattern;

        $calledFail = false;
        $rule->validate('pattern', '', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_handles_patterns_with_different_delimiters(): void
    {
        $rule = new ValidAutoLinkPattern;

        $patternsWithDelimiters = [
            '/pattern/',
            '#pattern#',
            '~pattern~',
            '{pattern}',
            '|pattern|',
        ];

        foreach ($patternsWithDelimiters as $pattern) {
            $calledFail = false;
            $rule->validate('pattern', $pattern, function ($message) use (&$calledFail) {
                $calledFail = true;
            });

            $this->assertFalse($calledFail, "デリミタ付きパターン「{$pattern}」は有効であるべき");
        }
    }
}
