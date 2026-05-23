<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\DB;

trait ResetsTenantRuntimeState
{
    protected function resetTenantRuntimeState(): void
    {
        try {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        } catch (\Throwable) {
        }

        foreach (['tenant', 'mysql_testing'] as $connection) {
            try {
                DB::disconnect($connection);
            } catch (\Throwable) {
            }

            try {
                DB::purge($connection);
            } catch (\Throwable) {
            }
        }
    }
}
