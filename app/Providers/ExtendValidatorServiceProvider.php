<?php

namespace App\Providers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class ExtendValidatorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Validator::extend('at_least_one_checked', function ($attribute, $value, $parameters, $validator) {
            // $value はチェックボックスの値の配列
            return count(array_filter($value)) > 0;
        });

        Validator::extend('in_options', function ($attribute, $value, $parameters, $validator) {
            // $parameters[0] にオプションの配列が渡される想定です
            $allowedOptions = $parameters[0] ?? [];

            if (!is_array($value)) {
                return false;
            }

            $value = array_filter($value);

            foreach ($value as $item) {
                if (!in_array($item, $allowedOptions)) {
                    return false;
                }
            }

            return true;
        });

    }
}
