<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetActivityLogTool;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Mcp\Request;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class GetActivityLogToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private GetActivityLogTool $tool;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tool = new GetActivityLogTool;

        // テストデータの準備
        $this->user = User::factory()->create();

        // トークン作成
        $token = $this->user->createToken('test-token');
        putenv('MCP_AUTH_TOKEN='.$token->plainTextToken);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        parent::tearDown();
    }

    public function test_rejects_missing_token(): void
    {
        putenv('MCP_AUTH_TOKEN=');

        $response = $this->tool->handle(
            new Request([])
        );

        $this->assertTrue($response->isError());
    }

    public function test_returns_empty_activities_with_proper_translation(): void
    {
        // 既存のアクティビティログ（ユーザー作成等）は存在する可能性があるが、
        // 存在しないledger_idでフィルタすれば空になる
        $response = $this->tool->handle(
            new Request([
                'format' => 'summary',
                'ledger_id' => 999999, // 存在しないID
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertArrayHasKey('activities', $content);
        $this->assertArrayHasKey('__summary__', $content);
        $this->assertArrayHasKey('__display_fields__', $content);
        $this->assertCount(0, $content['activities']);
        $this->assertEquals(0, $content['total_count']);
    }

    public function test_returns_activities_with_raw_format(): void
    {
        // アクティビティログを作成
        activity()
            ->causedBy($this->user)
            ->log('test_action');

        $response = $this->tool->handle(
            new Request([
                'format' => 'raw',
                'limit' => 10,
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertArrayHasKey('activities', $content);
        $this->assertArrayHasKey('__summary__', $content);
        $this->assertArrayHasKey('__display_fields__', $content);
        $this->assertGreaterThan(0, count($content['activities']));
    }

    public function test_filters_by_ledger_id(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
        ]);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $this->user->id,
        ]);

        // 台帳に関連するアクティビティを作成
        activity()
            ->performedOn($ledger)
            ->causedBy($this->user)
            ->log('ledger_created');

        // 別の台帳のアクティビティも作成
        $anotherLedger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $this->user->id,
        ]);
        activity()
            ->performedOn($anotherLedger)
            ->causedBy($this->user)
            ->log('ledger_created');

        $response = $this->tool->handle(
            new Request([
                'ledger_id' => $ledger->id,
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertGreaterThan(0, count($content['activities']));
        // 全てのアクティビティが指定した台帳のものであることを確認
        foreach ($content['activities'] as $activity) {
            if ($activity['subject_type'] === 'App\\Models\\Ledger') {
                $this->assertEquals($ledger->id, $activity['subject_id']);
            }
        }
    }

    public function test_filters_by_user_id(): void
    {
        $anotherUser = User::factory()->create();

        // 異なるユーザーによるアクティビティを作成
        activity()
            ->causedBy($this->user)
            ->log('user1_action');

        activity()
            ->causedBy($anotherUser)
            ->log('user2_action');

        $response = $this->tool->handle(
            new Request([
                'user_id' => $this->user->id,
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertGreaterThan(0, count($content['activities']));
        // 全てのアクティビティが指定したユーザーのものであることを確認
        foreach ($content['activities'] as $activity) {
            $this->assertEquals($this->user->id, $activity['causer_id']);
        }
    }

    public function test_respects_limit_parameter(): void
    {
        // 5件のアクティビティを作成
        for ($i = 0; $i < 5; $i++) {
            activity()
                ->causedBy($this->user)
                ->log("action_{$i}");
        }

        $response = $this->tool->handle(
            new Request([
                'limit' => 3,
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        // limitパラメータが適用されて3件だけ返ることを確認
        $this->assertCount(3, $content['activities']);
        $this->assertEquals(3, $content['total_count']);
    }

    public function test_activity_includes_proper_fields(): void
    {
        activity()
            ->causedBy($this->user)
            ->log('test_action');

        $response = $this->tool->handle(
            new Request([
                'limit' => 1,  // 最新の1件だけ取得
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertGreaterThan(0, count($content['activities']));

        $activity = $content['activities'][0];
        $this->assertArrayHasKey('id', $activity);
        $this->assertArrayHasKey('event', $activity);
        $this->assertArrayHasKey('event_label', $activity);
        $this->assertArrayHasKey('causer_name', $activity);
        $this->assertArrayHasKey('created_at', $activity);
        $this->assertArrayHasKey('created_at_formatted', $activity);
        $this->assertArrayHasKey('changes', $activity);
        $this->assertArrayHasKey('comment', $activity);
    }

    public function test_uses_translation_keys_for_display_fields(): void
    {
        $response = $this->tool->handle(
            new Request([
                'format' => 'summary',
                'ledger_id' => 999999, // 存在しないIDでフィルタして空にする
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertArrayHasKey('__display_fields__', $content);
        $displayFields = $content['__display_fields__'];

        $this->assertIsArray($displayFields);
        $this->assertArrayHasKey('time', $displayFields);
        $this->assertArrayHasKey('causer', $displayFields);
        $this->assertArrayHasKey('operation', $displayFields);
        $this->assertArrayHasKey('changes', $displayFields);

        // 翻訳された値であることを確認
        foreach ($displayFields as $field => $label) {
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }

    public function test_filters_by_event_type(): void
    {
        // 異なるイベントタイプでアクティビティを作成
        activity()
            ->causedBy($this->user)
            ->event('created')
            ->log('test_created');

        activity()
            ->causedBy($this->user)
            ->event('updated')
            ->log('test_updated');

        $response = $this->tool->handle(
            new Request([
                'event_type' => 'created',
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertGreaterThan(0, count($content['activities']));
        // 全てのアクティビティが指定したイベントタイプであることを確認
        foreach ($content['activities'] as $activity) {
            $this->assertEquals('created', $activity['event']);
        }
    }

    public function test_filters_by_date_range(): void
    {
        // 昨日のアクティビティを作成
        $yesterday = now()->subDay();
        $yesterdayActivity = activity()
            ->causedBy($this->user)
            ->log('yesterday_action');

        // 手動で昨日の日時に更新
        CustomActivity::where('id', $yesterdayActivity->id)
            ->update(['created_at' => $yesterday]);

        // 今日のアクティビティを作成
        activity()
            ->causedBy($this->user)
            ->log('today_action');

        $response = $this->tool->handle(
            new Request([
                'start_date' => now()->startOfDay()->toDateString(),
                'end_date' => now()->endOfDay()->toDateString(),
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        // 今日のアクティビティのみ取得されることを確認
        $this->assertGreaterThan(0, count($content['activities']));
        foreach ($content['activities'] as $activity) {
            $activityDate = Carbon::parse($activity['created_at'])->toDateString();
            $this->assertEquals(now()->toDateString(), $activityDate);
        }
    }
}
