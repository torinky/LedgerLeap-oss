<?php

namespace Tests\Feature;

use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Tag;
use App\Models\Tenant;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    /**
     * テナントに属する全モデルを定義
     */
    protected function getTenantModels(): array
    {
        return [
            Ledger::class,
            LedgerDiff::class,
            LedgerDefine::class,
            Folder::class,
            Tag::class,
            AttachedFile::class,
        ];
    }

    /**
     * 各モデルが正しく tenant_id を持っているか検証する
     */
    public function test_all_tenant_models_have_valid_tenant_id(): void
    {
        $tenantId = static::$sharedTenant->id;

        foreach ($this->getTenantModels() as $modelClass) {
            // テナントコンテキスト内で作成
            // Ledger などの一部モデルで factory()->create() が失敗する場合の対策
            try {
                $attributes = [];
                if ($modelClass === Ledger::class) {
                    $attributes = ['version' => 1, 'status' => \App\Enums\WorkflowStatus::DRAFT];
                }

                $model = $modelClass::factory()->create($attributes);
            } catch (\Exception $e) {
                $this->fail("Failed to create factory for {$modelClass}: ".$e->getMessage());
            }

            // 手動作成でも自動的に tenant_id が入っているはず
            $this->assertNotNull($model->tenant_id, "Model {$modelClass} has NULL tenant_id");
            $this->assertEquals($tenantId, $model->tenant_id, "Model {$modelClass} has incorrect tenant_id: Expected {$tenantId}, Got {$model->tenant_id}");

            // データベースから再取得してフィルタリングされないことを確認
            $fetched = $modelClass::find($model->id);
            $this->assertNotNull($fetched, "Model {$modelClass} with ID {$model->id} could not be found within tenant context (possibly filtered out due to missing/incorrect tenant_id)");
        }
    }

    /**
     * LedgerDiff が親である Ledger と同じ tenant_id を持っているか検証する
     */
    public function test_ledger_diff_tenant_id_matches_parent_ledger(): void
    {
        $tenantId = static::$sharedTenant->id;

        $ledger = Ledger::factory()->create([
            'version' => 1,
            'status' => \App\Enums\WorkflowStatus::DRAFT,
        ]);
        $this->assertEquals($tenantId, $ledger->tenant_id);

        $diff = LedgerDiff::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'content' => $ledger->content,
            'column_define' => $ledger->define->column_define,
            'version' => $ledger->version,
            'status' => \App\Enums\WorkflowStatus::DRAFT,
            'creator_id' => $ledger->creator_id,
            'modifier_id' => $ledger->modifier_id,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);

        $this->assertEquals($tenantId, $diff->tenant_id, 'LedgerDiff failed to inherit tenant_id from context/parent during manual creation');

        // 別のテナントに切り替えて、取得できないことを確認
        $otherTenant = Tenant::factory()->create(['id' => 'other-tenant']);
        // ドメインも必要
        $otherTenant->domains()->create(['domain' => 'other.localhost']);

        tenancy()->initialize($otherTenant);

        $this->assertNull(LedgerDiff::find($diff->id), 'LedgerDiff was visible in wrong tenant context');
    }

    /**
     * 不整合データの検知テスト
     * 本来は起こってはならないが、既存データに不整合があればこのテストで気づけるようにする
     */
    public function test_detect_inconsistent_tenant_ids(): void
    {
        // 管理者として「全テナント」のデータをチェック（withoutGlobalScopes）
        foreach ($this->getTenantModels() as $modelClass) {
            $inconsistent = $modelClass::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->orWhere('tenant_id', '')
                ->get();

            $this->assertCount(0, $inconsistent, 'Found '.$inconsistent->count()." records of {$modelClass} with missing tenant_id");
        }
    }
}
