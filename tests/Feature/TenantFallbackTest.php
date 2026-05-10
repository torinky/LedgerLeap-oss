<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Tests\TestCase;

class TenantFallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_to_login_when_route_is_missing_tenant_parameter(): void
    {
        // このテストのためだけに、意図的に不正なテナントルートを定義する
        Route::middleware(['web', InitializeTenancyByPath::class])
            ->get('/{dummy_param}/test-fallback', function () {
                return 'You should not see this.';
            });

        // 1. ユーザーを作成し、認証状態にする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2. テナントミドルウェアが適用されるが、最初のパラメータが tenant ではないパスへリクエストを送信する
        $response = $this->get('/foo/test-fallback');

        // 3. レスポンスが `login` ルートへのリダイレクトであることを検証する
        $response->assertRedirectToRoute('login');

        // 4. セッションに `info` のキーで正しいメッセージが格納されていることを検証する
        $response->assertSessionHas('info', __('messages.login_again_for_tenant'));
    }
}
