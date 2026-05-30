<?php

namespace Tests\Unit\Rules;

use App\Rules\RequiredCheckbox;
use Tests\TestCase;

class RequiredCheckboxTest extends TestCase
{
    public function test_it_passes_when_at_least_one_checkbox_is_checked(): void
    {
        $rule = new RequiredCheckbox;

        $calledFail = false;
        $rule->validate('checkboxes', ['option1'], function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_fails_when_no_checkbox_is_checked(): void
    {
        $rule = new RequiredCheckbox;

        $calledFail = false;
        $rule->validate('checkboxes', [], function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_fails_when_all_values_are_empty_strings(): void
    {
        $rule = new RequiredCheckbox;

        $calledFail = false;
        $rule->validate('checkboxes', ['', '', ''], function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_passes_when_multiple_checkboxes_are_checked(): void
    {
        $rule = new RequiredCheckbox;

        $calledFail = false;
        $rule->validate('checkboxes', ['option1', 'option2', 'option3'], function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_filters_out_empty_strings_before_counting(): void
    {
        $rule = new RequiredCheckbox;

        $calledFail = false;
        $rule->validate('checkboxes', ['option1', '', ''], function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_treats_zero_as_valid_value(): void
    {
        $rule = new RequiredCheckbox;

        // 文字列の '0'
        $calledFail = false;
        $rule->validate('checkboxes', ['0'], function ($message) use (&$calledFail) {
            $calledFail = true;
        });
        $this->assertFalse($calledFail);

        // 数値の 0
        $calledFail = false;
        $rule->validate('checkboxes', [0], function ($message) use (&$calledFail) {
            $calledFail = true;
        });
        $this->assertFalse($calledFail);
    }

    public function test_it_handles_mixed_valid_and_empty_values(): void
    {
        $rule = new RequiredCheckbox;

        $calledFail = false;
        $rule->validate('checkboxes', ['option1', '', 'option2', '', 'option3'], function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_handles_array_with_only_null_values(): void
    {
        $rule = new RequiredCheckbox;

        $calledFail = false;
        $rule->validate('checkboxes', [null, null, null], function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_handles_single_checkbox_scenario(): void
    {
        $rule = new RequiredCheckbox;

        // チェックされている場合
        $calledFail = false;
        $rule->validate('checkbox', ['checked'], function ($message) use (&$calledFail) {
            $calledFail = true;
        });
        $this->assertFalse($calledFail);

        // チェックされていない場合
        $calledFail = false;
        $rule->validate('checkbox', [''], function ($message) use (&$calledFail) {
            $calledFail = true;
        });
        $this->assertTrue($calledFail);
    }
}
