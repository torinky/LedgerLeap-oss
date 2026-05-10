<?php

namespace Tests\Unit\Mcp\Servers;

use App\Mcp\Servers\LedgerLeapServer;
use App\Mcp\Tools\ReadMcpResourceTool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(LedgerLeapServer::class)]
class LedgerLeapServerTest extends TestCase
{
    #[Test]
    public function it_registers_the_resource_bridge_tool(): void
    {
        $server = new class(new FakeTransporter) extends LedgerLeapServer {};

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('tools');

        /** @var array<int, class-string> $tools */
        $tools = $property->getValue($server);

        $this->assertContains(ReadMcpResourceTool::class, $tools);
    }
}
