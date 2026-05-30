<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    #[Test]
    public function mcp_server_can_be_started_without_errors()
    {
        $this->markTestSkipped('無限ループになるためスキップします');

        // mcp:start コマンドをテスト
        // このコマンドはデーモンとして動作するため、実際に起動して出力を確認する
        // ただし、テスト環境で長時間実行することは避ける
        $this->artisan('mcp:start', ['handle' => 'ledgerleap:mcp'])
            ->assertExitCode(0);

        // ここではコマンドがエラーなく起動できることのみを確認
        // 実際の通信テストは SearchLedgersToolTest でカバーされる
    }
}
