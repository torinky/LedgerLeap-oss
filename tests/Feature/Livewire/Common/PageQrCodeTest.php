<?php

namespace Tests\Feature\Livewire\Common;

use App\Livewire\Common\PageQrCode;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

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
            ->assertSee(__('ledger.page_qr_code.modal_title'));
    }

    #[Test]
    public function it_renders_correctly_with_filament_trigger()
    {
        Livewire::test(PageQrCode::class, ['triggerType' => 'filament'])
            ->assertStatus(200)
            ->assertSee(__('ledger.page_qr_code.modal_title'))
            ->assertSee('mountAction(\'qrCode\')'); // Filament action trigger
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
            ->assertSeeHtml('<svg') // QR code SVG
            ->assertSee($url);
    }
}
