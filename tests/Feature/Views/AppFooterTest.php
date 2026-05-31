<?php

namespace Tests\Feature\Views;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppFooterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function footer_renders_version_label_and_value(): void
    {
        $html = view('partials.app-footer')->render();

        $this->assertStringContainsString(__('ledger.footer.version'), $html);
        $this->assertStringContainsString(config('ledgerleap.version'), $html);
    }

    #[Test]
    public function version_links_to_changelog()
    {
        $html = view('partials.app-footer')->render();

        $this->assertStringContainsString(
            'href="https://github.com/torinky/LedgerLeap-oss/blob/main/CHANGELOG.md"',
            $html,
        );
    }

    #[Test]
    public function version_link_has_target_blank_and_rel_noopener()
    {
        $html = view('partials.app-footer')->render();

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    #[Test]
    public function footer_always_renders_id_app_footer()
    {
        $html = view('partials.app-footer')->render();

        $this->assertStringContainsString('id="app-footer"', $html);
    }

    #[Test]
    public function footer_renders_copyright_notice()
    {
        $html = view('partials.app-footer')->render();

        $this->assertStringContainsString('©', $html);
        $this->assertStringContainsString(__('ledger.footer.all_rights_reserved'), $html);
    }

    #[Test]
    public function footer_version_is_displayed_on_full_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('global.my-portal'));

        $response->assertOk();
        $response->assertSee(__('ledger.footer.version'));
        $response->assertSee(config('ledgerleap.version'));
    }
}
