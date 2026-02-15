<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RequiredCheckbox implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        //        dd($value,count($value),count(array_filter($value,'strlen')));
        if (count(array_filter($value, 'strlen')) == 0) {
            if (app()->runningUnitTests()) {
                $fail('validation.required');
            } else {
                $fail('validation.required')->translate();
            }
        }
    }
}
