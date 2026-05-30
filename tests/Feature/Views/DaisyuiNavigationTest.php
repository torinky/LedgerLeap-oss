<?php

namespace Tests\Feature\Views;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DaisyuiNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function profile_menu_contains_version_link()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('global.my-portal'));

        $response->assertOk();
        $response->assertSee(config('ledgerleap.version'));
    }

    #[Test]
    public function profile_menu_version_links_to_changelog()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('global.my-portal'));

        $response->assertOk();
        $response->assertSee(
            'href="https://github.com/torinky/LedgerLeap-oss/blob/main/CHANGELOG.md"',
            false,
        );
    }

    #[Test]
    public function profile_menu_version_link_has_target_blank_and_rel_noopener()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('global.my-portal'));

        $response->assertOk();
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
    }
}
