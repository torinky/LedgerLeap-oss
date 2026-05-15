<?php

namespace Tests\Unit\Mcp\Traits;

use App\Mcp\Traits\TruncatableResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TruncatableResponseTest extends TestCase
{
    /**
     * テスト用: トレイトメソッドを呼び出せるようにするラッパー
     */
    private function callTruncateIfNeeded(array $data): array
    {
        $obj = new class
        {
            use TruncatableResponse;

            public function truncate(array $data): array
            {
                return $this->truncateIfNeeded($data);
            }
        };

        return $obj->truncate($data);
    }

    #[Test]
    public function it_returns_data_unchanged_when_under_limit(): void
    {
        $data = [
            'ledgers' => [
                ['id' => 1, 'title' => 'test'],
            ],
            'total' => 1,
        ];

        $result = $this->callTruncateIfNeeded($data);

        $this->assertArrayNotHasKey('__truncated__', $result);
        $this->assertEquals($data, $result);
    }

    #[Test]
    public function it_removes_search_trace_first(): void
    {
        $data = [
            'ledgers' => [['id' => 1]],
            'total' => 1,
            'search_trace' => array_fill(0, 1000, ['term' => 'test', 'kind' => 'original', 'reason' => 'Direct match with additional verbose explanation text']),
        ];

        $result = $this->callTruncateIfNeeded($data);

        $this->assertTrue($result['__truncated__'] ?? false);
        $this->assertArrayNotHasKey('search_trace', $result);
        $this->assertContains('search_trace', $result['__truncated_at__'] ?? []);
    }

    #[Test]
    public function it_removes_payload_structured_and_visual(): void
    {
        $attachments = [];
        for ($i = 0; $i < 20; $i++) {
            $attachments[] = [
                'attachment_id' => $i,
                'payloads' => [
                    'text' => ['text' => str_repeat('x', 200), 'lines' => []],
                    'structured' => array_fill(0, 50, ['page_index' => $i, 'text' => str_repeat('data', 50)]),
                    'visual' => ['mime_type' => 'image/png', 'signed_url' => 'http://example.com/'.str_repeat('x', 200)],
                ],
            ];
        }

        $data = [
            'ledgers' => [
                ['id' => 1, 'attachments' => $attachments],
            ],
            'total' => 1,
        ];

        $result = $this->callTruncateIfNeeded($data);

        $this->assertTrue($result['__truncated__'] ?? false);
        // structured / visual are removed from payloads
        foreach ($result['ledgers'][0]['attachments'] as $att) {
            $this->assertArrayNotHasKey('structured', $att['payloads']);
            $this->assertArrayNotHasKey('visual', $att['payloads']);
        }
    }

    #[Test]
    public function it_removes_payload_text_lines(): void
    {
        $attachments = [];
        for ($i = 0; $i < 30; $i++) {
            $lines = [];
            for ($j = 0; $j < 100; $j++) {
                $lines[] = ['line_number' => $j + 1, 'text' => str_repeat('line data here ', 10)];
            }

            $attachments[] = [
                'attachment_id' => $i,
                'payloads' => [
                    'text' => [
                        'text' => str_repeat('t', 100),
                        'lines' => $lines,
                    ],
                ],
            ];
        }

        $data = [
            'ledgers' => [
                ['id' => 1, 'attachments' => $attachments],
            ],
            'total' => 1,
        ];

        $result = $this->callTruncateIfNeeded($data);

        $this->assertTrue($result['__truncated__'] ?? false);
        // lines are removed from text payloads
        foreach ($result['ledgers'][0]['attachments'] as $att) {
            $this->assertArrayNotHasKey('lines', $att['payloads']['text']);
        }
    }

    #[Test]
    public function it_removes_meta_column_defines(): void
    {
        $ledgers = [];
        for ($i = 0; $i < 20; $i++) {
            $ledgers[] = ['id' => $i, 'title' => str_repeat("ledger {$i} ", 50)];
        }

        $columnDefines = [];
        for ($i = 0; $i < 50; $i++) {
            $columnDefines[] = [
                'id' => $i,
                'name' => str_repeat("column_{$i} ", 30),
                'type' => 'text',
                'options' => array_fill(0, 20, str_repeat('opt ', 20)),
            ];
        }

        $data = [
            'ledgers' => $ledgers,
            'total' => 20,
            'meta' => [
                'ledger_defines' => [
                    1 => ['id' => 1, 'name' => 'Test Define', 'column_define' => $columnDefines],
                ],
            ],
        ];

        $result = $this->callTruncateIfNeeded($data);

        $this->assertTrue($result['__truncated__'] ?? false);
        // column_define is removed from meta.ledger_defines
        if (isset($result['meta']['ledger_defines'])) {
            foreach ($result['meta']['ledger_defines'] as $define) {
                $this->assertArrayNotHasKey('column_define', $define);
            }
        }
    }

    #[Test]
    public function it_reduces_ledgers_tail_as_last_resort(): void
    {
        $ledgers = [];
        for ($i = 0; $i < 50; $i++) {
            $ledgers[] = [
                'id' => $i,
                'title' => str_repeat("ledger {$i} title with some extra text ", 30),
                'content' => array_fill(0, 10, str_repeat('content value here ', 20)),
            ];
        }

        $data = [
            'ledgers' => $ledgers,
            'total' => 50,
        ];

        $result = $this->callTruncateIfNeeded($data);

        $this->assertTrue($result['__truncated__'] ?? false);
        $this->assertArrayHasKey('__truncated_items__', $result);
        $this->assertGreaterThan(0, $result['__truncated_items__']);
        $this->assertLessThan(50, count($result['ledgers']));
    }

    #[Test]
    public function it_produces_valid_json_after_truncation(): void
    {
        $ledgers = [];
        for ($i = 0; $i < 50; $i++) {
            $ledgers[] = [
                'id' => $i,
                'title' => str_repeat("large ledger {$i} ", 50),
                'content' => array_fill(0, 10, str_repeat('content data ', 50)),
            ];
        }

        $data = [
            'ledgers' => $ledgers,
            'total' => 50,
            'search_trace' => array_fill(0, 500, ['term' => 'test', 'kind' => 'original']),
            'meta' => [
                'ledger_defines' => array_fill(0, 20, [
                    'id' => 1,
                    'name' => 'Test',
                    'column_define' => array_fill(0, 30, ['id' => 1, 'name' => 'col', 'type' => 'text', 'options' => []]),
                ]),
            ],
        ];

        $result = $this->callTruncateIfNeeded($data);

        // 有効なJSONであること（json_decode でエラーなし）
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertTrue($decoded['__truncated__'] ?? false);
    }
}
