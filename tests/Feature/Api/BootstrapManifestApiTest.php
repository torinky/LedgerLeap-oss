<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\V1\BootstrapManifestController;
use App\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

#[CoversClass(BootstrapManifestController::class)]
class BootstrapManifestApiTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->make(['id' => 1]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_bootstrap_manifest(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->getJson('/api/v1/ai/bootstrap-manifest?client_type=copilot&role_profile=operator')
            ->assertUnauthorized();
    }

    #[Test]
    public function authenticated_user_can_resolve_bootstrap_manifest_via_get(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->getJson(
                '/api/v1/ai/bootstrap-manifest?client_type=copilot&role_profile=operator'
                .'&model_profile=small-local&language=ja'
            );

        $response->assertOk()
            ->assertJsonPath('data.client_type', 'copilot')
            ->assertJsonPath('data.role_profile.id', 'operator')
            ->assertJsonPath('data.model_profile.id', 'small-local')
            ->assertJsonCount(3, 'data.recommended_capabilities')
            ->assertJsonPath('data.warnings', []);
    }

    #[Test]
    public function authenticated_user_can_resolve_bootstrap_manifest_via_post(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->postJson('/api/v1/ai/bootstrap-manifest/resolve', [
                'client_type' => 'openai-agents',
                'role_profile' => 'administrator',
                'model_profile' => 'general-local',
                'language' => 'ja',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.client_type', 'openai-agents')
            ->assertJsonPath('data.role_profile.id', 'administrator')
            ->assertJsonPath('data.model_profile.id', 'general-local')
            ->assertJsonCount(4, 'data.recommended_capabilities')
            ->assertJsonFragment(['id' => 'activity-audit'])
            ->assertJsonFragment(['id' => 'analytics-report'])
            ->assertJsonPath('data.files.0.relative_path', 'templates/ledger_agents.py');
    }

    #[Test]
    public function it_validates_required_bootstrap_manifest_inputs(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->getJson('/api/v1/ai/bootstrap-manifest?client_type=unknown-client&role_profile=operator');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_type']);
    }
}
