<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateDemoMcpToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:generate-mcp-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate MCP authentication token for demo user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $demoUser = User::where('email', 'demo@example.com')->first();

        if (! $demoUser) {
            $this->error('Demo user (demo@example.com) not found!');
            $this->info('Please run: php artisan db:seed --class=DemoMinimalSeeder');

            return 1;
        }

        // 既存のトークンを削除
        $deletedCount = $demoUser->tokens()->delete();
        if ($deletedCount > 0) {
            $this->info("Deleted {$deletedCount} existing token(s)");
        }

        // 新しいトークンを作成
        $token = $demoUser->createToken('mcp-demo-token', ['mcp:*']);

        $this->info('');
        $this->info('✅ MCP Token generated successfully!');
        $this->info('');
        $this->info('User: '.$demoUser->name.' ('.$demoUser->email.')');
        $this->info('Token: '.$token->plainTextToken);
        $this->info('');
        $this->info('Add this to your .env file:');
        $this->line('MCP_AUTH_TOKEN='.$token->plainTextToken);
        $this->info('');

        return 0;
    }
}
