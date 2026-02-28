<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ActivityLogFormatter;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ActivityLogFormatterLinkTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // Ledger::factory()->create() が LedgerObserver 経由で ProcessLedgerForRagJob を
        // dispatch する。Queue::fake() でジョブを実際には実行させず、
        // Embeddingコンテナへの接続を防ぐ（このテストの責務外）。
        Queue::fake();
    }

    #[Test]
    public function it_returns_link_when_tenant_exists()
    {
        // Setup scenarios
        $ledger = Ledger::factory()->create();
        $activity = activity()->performedOn($ledger)->log('test');

        $link = ActivityLogFormatter::getSubjectDetailLink($activity);

        $expected = route('ledger.show', ['tenant' => tenant()->id, 'ledgerId' => $ledger->id]);
        $this->assertEquals($expected, $link);
    }

    #[Test]
    public function it_returns_null_when_tenant_context_is_missing()
    {
        $ledger = Ledger::factory()->create();
        $activity = activity()->performedOn($ledger)->log('test');

        // Forget current tenant to simulate missing context
        tenancy()->end();

        // This should NOT throw an exception, but return null
        $link = ActivityLogFormatter::getSubjectDetailLink($activity);

        $this->assertNull($link);
    }

    #[Test]
    public function it_returns_link_for_ledger_define()
    {
        $define = LedgerDefine::factory()->create();
        $activity = activity()->performedOn($define)->log('test');

        $link = ActivityLogFormatter::getSubjectDetailLink($activity);

        $expected = route('ledgersByDefineId', ['tenant' => tenant()->id, 'defineId' => $define->id]);
        $this->assertEquals($expected, $link);
    }

    #[Test]
    public function it_returns_link_for_folder()
    {
        $folder = Folder::factory()->create();
        $activity = activity()->performedOn($folder)->log('test');

        $link = ActivityLogFormatter::getSubjectDetailLink($activity);

        $expected = route('ledgersByFolderId', ['tenant' => tenant()->id, 'folderId' => $folder->id]);
        $this->assertEquals($expected, $link);
    }
}
