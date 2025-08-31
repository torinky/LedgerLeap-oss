<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-tenant {tenant_id : The ID of the tenant} {admin_email : The email of the admin user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant and run all necessary setup processes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
