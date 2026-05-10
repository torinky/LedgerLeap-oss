<?php

namespace Tests\Feature\Livewire\Common;

use App\Livewire\Common\PageQrCode;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\Ledger\LedgerShareUrlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(PageQrCode::class)]
class PageQrCodeTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_renders_correctly_with_mary_trigger()
    {
        Livewire::test(PageQrCode::class, ['triggerType' => 'mary'])
            ->assertStatus(200)
            ->assertSee(__('ledger.page_qr_code.modal_title'))
            ->assertSeeHtml('backdrop-blur z-70')
            ->assertSeeHtml('max-w-4xl z-80')
            ->assertSeeHtml('open-page-qr-code-from-inspector.window');
    }

    #[Test]
    public function it_renders_correctly_with_filament_trigger()
    {
        Livewire::test(PageQrCode::class, ['triggerType' => 'filament'])
            ->assertStatus(200)
            ->assertSeeHtml('fi-ac-icon-btn-action')
            ->assertSee(__('ledger.page_qr_code.modal_title'));
    }

    #[Test]
    public function it_opens_modal_and_generates_qr_code()
    {
        $url = 'https://example.com/test?foo=bar';

        Livewire::test(PageQrCode::class)
            ->assertSet('showModal', false)
            ->call('openModal', $url)
            ->assertSet('showModal', true)
            ->assertSet('url', $url)
            ->assertDispatched('page-qr-code-url-synced', url: $url)
            ->assertSeeHtml('<svg') // QR code SVG
            ->assertSeeHtml('timeout: 2400')
            ->assertSee(__('ledger.qr_share.url_label'))
            ->assertDontSee(__('ledger.prefill.url_label'))
            ->assertSee($url);
    }

    #[Test]
    public function it_canonicalizes_known_ledger_urls_before_showing_qr_code()
    {
        $folder = Folder::factory()->create([
            'title' => '共有フォルダ',
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => '契約台帳',
            'folder_id' => $folder->id,
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $url = route('ledger.show', [
            'tenant' => $this->getTenant()->id,
            'ledgerId' => $ledger->id,
            'highlight' => 'keyword',
            'tab' => 'history',
            'sc' => 1,
            'file' => 123,
        ]);

        $component = Livewire::test(PageQrCode::class)
            ->call('openModal', $url);

        $expected = app(LedgerShareUrlService::class)->canonicalize($url);

        $this->assertSame($expected, $component->instance()->url);
        $this->assertStringContainsString('highlight=keyword', $component->instance()->url);
        $this->assertStringContainsString('file=123', $component->instance()->url);
    }

    #[Test]
    public function it_preserves_list_scope_selection_parameters_when_opening_qr_code()
    {
        $folder = Folder::factory()->create([
            'title' => '共有フォルダ',
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => '共有台帳',
            'folder_id' => $folder->id,
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $url = route('ledger.index', [
            'tenant' => $this->getTenant()->id,
            'q' => 'search-term',
            'dir' => true,
            'dl' => 3,
            'sort' => 'content->2',
            'l' => [$ledgerDefine->id],
            'f' => [$folder->id],
            'cf' => $folder->id,
            'tt' => false,
            'file' => 456,
        ]);

        $component = Livewire::test(PageQrCode::class)
            ->call('openModal', $url);

        $this->assertSame(
            app(LedgerShareUrlService::class)->canonicalize($url),
            $component->instance()->url,
        );
        $this->assertStringContainsString('l%5B0%5D='.$ledgerDefine->id, $component->instance()->url);
        $this->assertStringContainsString('f%5B0%5D='.$folder->id, $component->instance()->url);
        $this->assertStringContainsString('cf='.$folder->id, $component->instance()->url);
        $this->assertStringContainsString('file=456', $component->instance()->url);
    }

    #[Test]
    public function it_falls_back_to_referer_when_opening_modal_without_explicit_url()
    {
        $url = 'https://example.com/current?tab=search';

        $originalRequest = app('request');
        $request = Request::create('/dummy', 'GET', server: ['HTTP_REFERER' => $url]);

        app()->instance('request', $request);

        try {
            $component = new PageQrCode;

            $this->assertFalse($component->showModal);

            $component->openModal();

            $this->assertTrue($component->showModal);
            $this->assertSame($url, $component->url);
            $this->assertStringContainsString('<svg', $component->qrCode());
        } finally {
            app()->instance('request', $originalRequest);
        }
    }

    #[Test]
    public function it_generates_contextual_download_file_name_from_target_url()
    {
        Carbon::setTestNow('2026-03-15 13:30:00');

        $folder = Folder::factory()->create([
            'title' => '共有フォルダ',
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => '契約/台帳:2026',
            'folder_id' => $folder->id,
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $component = Livewire::test(PageQrCode::class)
            ->call('openModal', route('ledger.edit', [
                'tenant' => $this->getTenant()->id,
                'ledgerId' => $ledger->id,
            ]));

        $this->assertSame(
            '契約_台帳_2026_台帳編集用QR_20260315_133000.svg',
            $component->instance()->downloadFileName()
        );

        Carbon::setTestNow();
    }

    #[Test]
    public function it_falls_back_to_generic_download_file_name_for_unknown_url()
    {
        Carbon::setTestNow('2026-03-15 13:30:00');

        $component = Livewire::test(PageQrCode::class)
            ->call('openModal', 'https://example.com/custom/share-page');

        $this->assertSame(
            '画面共有用QR_20260315_133000.svg',
            $component->instance()->downloadFileName()
        );

        Carbon::setTestNow();
    }

    #[Test]
    public function it_uses_prefill_modal_on_duplicate_create_screen()
    {
        $folder = Folder::factory()->create([
            'title' => '複製元フォルダ',
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledgerDefine = LedgerDefine::factory()->create([
            'title' => '複製元台帳',
            'folder_id' => $folder->id,
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'tenant_id' => $this->getTenant()->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $originalRequest = app('request');
        $request = Request::create(route('ledger.duplicate', [
            'tenant' => $this->getTenant()->id,
            'ledgerId' => $ledger->id,
        ]), 'GET');
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);

        try {
            $component = new PageQrCode;
            $component->mount();

            $this->assertTrue($component->isLedgerEditScreen);

            $component->openModal();

            $this->assertFalse($component->showModal);
            $this->assertSame('', $component->url);
        } finally {
            app()->instance('request', $originalRequest);
        }
    }

    #[Test]
    public function it_renders_filament_modal_content_with_generic_share_labels()
    {
        $this->view('livewire.common.page-qr-code-modal-content', [
            'url' => 'https://example.com/shared',
            'qrCode' => '<svg></svg>',
            'downloadName' => 'test.svg',
        ])
            ->assertSee(__('ledger.qr_share.url_label'))
            ->assertSee(__('ledger.qr_share.qr_code_title'))
            ->assertDontSee(__('ledger.page_qr_code.modal_title'))
            ->assertDontSee(__('ledger.prefill.url_label'));
    }
}
