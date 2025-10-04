<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Tools\GetPendingApprovalsTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\WorkflowService;
use Laravel\Mcp\Request;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * GetPendingApprovalsToolの詳細テスト
 */
class GetPendingApprovalsToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected GetPendingApprovalsTool $tool;

    protected User $user;

    protected PersonalAccessToken $accessToken;

    protected Folder $folder;

    protected LedgerDefine $ledgerDefine;

    protected string $plainTextToken;

    protected WorkflowService $workflowService;

    protected function setUp(): void
    {
        parent::setUp();

        // テナント作成・初期化
        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $this->tool = new GetPendingApprovalsTool;

        // テスト用ユーザー作成
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // アクセストークン作成
        $tokenResult = $this->user->createToken('test-token');
        $this->accessToken = $tokenResult->accessToken;
        $this->plainTextToken = $tokenResult->plainTextToken;

        // テスト用フォルダ・台帳定義作成
        $this->folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'title' => 'Test Ledger Define',
            'folder_id' => $this->folder->id,
        ]);

        $this->workflowService = $this->mock(WorkflowService::class);
    }

    #[Test]
    public function it_returns_empty_results_with_proper_translation(): void
    {
        putenv('MCP_AUTH_TOKEN='.$this->plainTextToken);

        $request = new Request(['format' => 'summary']);

        $response = $this->tool->handle($request, $this->workflowService);

        $this->assertFalse($response->isError(), 'Response should not be error');

        $responseData = json_decode($response->content()->__toString(), true);
        $this->assertIsArray($responseData, 'Response should be valid JSON array');

        $this->assertEquals(0, $responseData['total_tasks']);
        $this->assertEquals(0, $responseData['inspection_count']);
        $this->assertEquals(0, $responseData['approval_count']);

        // 翻訳キーが使用されていることを確認
        $this->assertStringContainsString('未処理', $responseData['__summary__']);
    }

    #[Test]
    public function it_uses_translation_keys_for_display_fields(): void
    {
        putenv('MCP_AUTH_TOKEN='.$this->plainTextToken);

        $request = new Request(['format' => 'summary']);
        $response = $this->tool->handle($request, $this->workflowService);

        $this->assertFalse($response->isError(), 'Response should not be error');

        $responseData = json_decode($response->content()->__toString(), true);
        $this->assertIsArray($responseData, 'Response should be valid JSON array');

        // __display_fields__に翻訳キーが適用されていることを確認
        $displayFields = $responseData['__display_fields__'];

        $this->assertIsArray($displayFields);
        $this->assertArrayHasKey('title', $displayFields);
        $this->assertArrayHasKey('status', $displayFields);
        $this->assertArrayHasKey('assignee', $displayFields);
        $this->assertArrayHasKey('age', $displayFields);

        // 翻訳された値であることを確認（空でない文字列）
        foreach ($displayFields as $field => $label) {
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
            $this->assertNotEquals($field, $label); // フィールド名とラベルが異なる
        }
    }

    #[Test]
    public function it_handles_request_without_format_parameter(): void
    {
        putenv('MCP_AUTH_TOKEN='.$this->plainTextToken);

        $request = new Request([]); // formatパラメータなし

        $response = $this->tool->handle($request, $this->workflowService);

        $this->assertFalse($response->isError(), 'Response should not be error');

        $responseData = json_decode($response->content()->__toString(), true);
        $this->assertIsArray($responseData, 'Response should be valid JSON array');

        // 基本的なレスポンス構造の確認
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertArrayHasKey('__display_fields__', $responseData);
        $this->assertArrayHasKey('pending_inspections', $responseData);
        $this->assertArrayHasKey('pending_approvals', $responseData);
        $this->assertArrayHasKey('total_tasks', $responseData);
    }

    #[Test]
    public function it_returns_pending_inspections_for_user(): void
    {
        putenv('MCP_AUTH_TOKEN='.$this->plainTextToken);

        // 点検待ちの台帳を作成（contentに適切なデータを設定）
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'content' => ['Test Pending Inspection'], // インデックス0にタイトルを設定
        ]);

        // LedgerDiffを作成（点検者として設定）
        $ledgerDiff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'inspector_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        $ledger->update(['latest_diff_id' => $ledgerDiff->id]);

        $request = new Request(['format' => 'summary']);

        $response = $this->tool->handle($request, $this->workflowService);

        $this->assertFalse($response->isError(), 'Response should not be error');

        $responseData = json_decode($response->content()->__toString(), true);

        // データの確認
        $this->assertEquals(1, $responseData['total_tasks']);
        $this->assertEquals(1, $responseData['inspection_count']);
        $this->assertEquals(0, $responseData['approval_count']);

        // タスクの詳細をチェック
        $tasks = $responseData['tasks'];
        $this->assertCount(1, $tasks);

        $this->assertEquals('Test Pending Inspection', $tasks[0]['title']);
        $this->assertEquals('inspection', $tasks[0]['type']);
        $this->assertArrayHasKey('priority', $tasks[0]);
        $this->assertArrayHasKey('age', $tasks[0]); // ResponseHelperでは'age'キーになっている
    }

    #[Test]
    public function it_returns_pending_approvals_for_user(): void
    {
        putenv('MCP_AUTH_TOKEN='.$this->plainTextToken);

        // 承認待ちの台帳を作成（contentに適切なデータを設定）
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
            'content' => ['Test Pending Approval'], // インデックス0にタイトルを設定
        ]);

        // LedgerDiffを作成（承認者として設定）
        $ledgerDiff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'approver_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);

        $ledger->update(['latest_diff_id' => $ledgerDiff->id]);

        $request = new Request(['format' => 'summary']);

        $response = $this->tool->handle($request, $this->workflowService);

        $this->assertFalse($response->isError(), 'Response should not be error');

        $responseData = json_decode($response->content()->__toString(), true);

        $this->assertEquals(1, $responseData['total_tasks']);
        $this->assertEquals(0, $responseData['inspection_count']);
        $this->assertEquals(1, $responseData['approval_count']);

        $tasks = $responseData['tasks'];
        $this->assertCount(1, $tasks);
        $this->assertEquals('Test Pending Approval', $tasks[0]['title']);
        $this->assertEquals('approval', $tasks[0]['type']);
        $this->assertArrayHasKey('priority', $tasks[0]);
        $this->assertArrayHasKey('age', $tasks[0]); // ResponseHelperでは'age'キーになっている
    }

    protected function tearDown(): void
    {
        // 環境変数をクリア
        putenv('MCP_AUTH_TOKEN=');
        parent::tearDown();
    }
}
