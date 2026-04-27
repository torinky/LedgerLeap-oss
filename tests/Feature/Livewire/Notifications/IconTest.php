<?php

namespace Tests\Feature\Livewire\Notifications;

use App\Livewire\Notifications\Icon;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(Icon::class)]
class IconTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        tenancy()->initialize($this->getTenant());

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_links_to_the_global_notifications_page(): void
    {
        Livewire::test(Icon::class)
            ->assertSeeHtml('href="'.route('notifications.index').'"');
    }
}
