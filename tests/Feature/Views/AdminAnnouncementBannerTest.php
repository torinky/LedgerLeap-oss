<?php

namespace Tests\Feature\Views;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAnnouncementBannerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function component_renders_actions_for_non_critical_announcements(): void
    {
        $html = view('components.admin.announcement-banner', [
            'announcement' => [
                'title' => 'メンテナンス予告',
                'body' => '明日 09:00 から 10:00 までシステムメンテナンスを実施します。',
                'level' => 'info',
                'dismiss_storage_key' => 'ledgerleap.test.banner',
                'links' => [
                    ['label' => __('ledger.details'), 'url' => '/announcements/maintenance'],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('data-admin-announcement-banner', $html);
        $this->assertStringContainsString('メンテナンス予告', $html);
        $this->assertStringContainsString('明日 09:00 から 10:00 までシステムメンテナンスを実施します。', $html);
        $this->assertStringContainsString('ledgerleap.test.banner', $html);
        $this->assertStringContainsString(__('ledger.details'), $html);
        $this->assertStringContainsString(__('ledger.close'), $html);
        $this->assertStringContainsString('x-cloak', $html);
    }

    #[Test]
    public function critical_announcements_render_without_dismiss_button(): void
    {
        $html = view('components.admin.announcement-banner', [
            'announcement' => [
                'title' => '障害連絡',
                'body' => '現在、ログイン処理に影響が発生しています。',
                'level' => 'critical',
                'dismiss_storage_key' => 'ledgerleap.test.banner.critical',
                'links' => [],
            ],
        ])->render();

        $this->assertStringContainsString('sticky top-0 z-50', $html);
        $this->assertStringContainsString('bg-error/20', $html);
        $this->assertStringNotContainsString(__('ledger.close'), $html);
    }

    #[Test]
    public function app_layout_includes_the_banner_when_configured(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        config([
            'ledgerleap.announcement_banner.current' => [
                'title' => '全体お知らせ',
                'body' => 'このページは公告バナーのレイアウト確認用です。',
                'level' => 'warning',
                'dismiss_storage_key' => 'ledgerleap.test.banner.layout',
                'links' => [
                    ['label' => __('ledger.details'), 'url' => '/announcements/layout-check'],
                ],
            ],
        ]);

        $html = view('layouts.app', [
            'slot' => new HtmlString('<div>page body</div>'),
            'attributes' => new ComponentAttributeBag([]),
        ])->render();

        $this->assertStringContainsString('data-admin-announcement-banner', $html);
        $this->assertStringContainsString('全体お知らせ', $html);
    }

    #[Test]
    public function drawer_layout_offsets_the_fixed_header_when_the_banner_is_present(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        config([
            'ledgerleap.announcement_banner.current' => [
                'title' => '全体お知らせ',
                'body' => 'このページは公告バナーのレイアウト確認用です。',
                'level' => 'warning',
                'dismiss_storage_key' => 'ledgerleap.test.banner.layout.drawer',
                'links' => [],
            ],
        ]);

        $html = view('layouts.appWithDrawer', [
            'slot' => new HtmlString('<div>page body</div>'),
            'attributes' => new ComponentAttributeBag([]),
            'drawer' => '',
        ])->render();

        $this->assertStringContainsString('style="top: var(--admin-announcement-banner-offset, 0px);"', $html);
        $this->assertStringContainsString('style="padding-top: calc(5rem + var(--admin-announcement-banner-offset, 0px));"', $html);
    }

    #[Test]
    public function preview_page_is_available_for_visual_checks(): void
    {
        $response = $this->get('/__preview/admin-announcement-banner?level=warning');

        $response->assertOk();
        $response->assertSee('Admin announcement banner', false);
        $response->assertSee('期限接近のお知らせ', false);
        $response->assertSee('data-admin-announcement-banner', false);
    }
}
