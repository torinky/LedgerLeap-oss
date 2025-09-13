<?php

namespace App\Filament\Widgets;

use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class DashboardLinksWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-links-widget';

    public ?string $from_tenant = null;

    protected int|string|array $columnSpan = [
        'sm' => 2,
        'md' => 3,
        'lg' => 4,
        'xl' => 6,
    ];

    protected function onTenantContext(): void
    {
        if ($this->from_tenant && !session()->has('tenant_context_notified_' . $this->from_tenant)) {
            $tenant = \App\Models\Tenant::find($this->from_tenant);
            if ($tenant) {
                Notification::make()
                    ->title(__('ledger.tenant_context_notification_title'))
                    ->body(__('ledger.tenant_context_notification_body', ['name' => $tenant->name ?: $tenant->id]))
                    ->info()
                    ->send();
                session()->put('tenant_context_notified_' . $this->from_tenant, true);
            }
        }
    }

    public function mount(): void
    {
        $this->from_tenant=session('filament_from_tenant_id');
        $this->onTenantContext();
    }

    public static function canView(): bool
    {
        return true; // 必要に応じて、表示条件を設定
    }

    protected function getViewData(): array
    {
        return [
            'groups' => $this->getGroups(),
        ];
    }

    protected function getGroups(): array
    {
        $groups = [];

        // URLから遷移元テナントIDを取得
        $fromTenantId = $this->from_tenant;

        // テナントIDが存在する場合、テナント固有の設定グループを配列の先頭に追加
        if ($fromTenantId && $tenant = \App\Models\Tenant::find($fromTenantId)) {
            $groups[] = [
                'title' => __('ledger.current_tenant') . ': ' . ($tenant->name ?: $tenant->id),
                'icon' => 'heroicon-o-identification',
                'links' => [
                    [
                        'title' => __('ledger.ledger_define'),
                        'icon' => 'heroicon-o-document-text',
                        'url' => route('ledgerDefine.index', ['tenant' => $fromTenantId]),
                        'color' => 'primary',
                    ],
                    [
                        'title' => __('ledger.settings.folder'),
                        'icon' => 'heroicon-o-folder',
                        'url' => route('filament.admin.resources.folders.index', ['tenant' => $fromTenantId]),
                        'color' => 'primary',
                    ],
                    [
                        'title' => __('ledger.navigation.back_to_tenant'),
                        'icon' => 'heroicon-o-arrow-uturn-left',
                        'url' => route('my-portal', ['tenant' => $fromTenantId]),
                        'color' => 'secondary',
                    ],
                ],
            ];
        }

        // 既存の中央管理設定グループ
        $centralGroups = [
            [
                'title' => __('ledger.settings.user_management'),
                'icon' => 'heroicon-o-users',
                'links' => [
                    [
                        'title' => __('ledger.user'),
                        'icon' => 'heroicon-o-user',
                        'url' => route('filament.admin.resources.users.index'),
                        'color' => 'danger',
                    ],
                    [
                        'title' => __('ledger.organization'),
                        'icon' => 'heroicon-o-building-office',
                        'url' => route('filament.admin.resources.organizations.index'),
                        'color' => 'warning',
                    ],
                ],
            ],
            [
                'title' => __('ledger.settings.access_control'),
                'icon' => 'heroicon-o-shield-check',
                'links' => [
                    [
                        'title' => __('ledger.role'),
                        'icon' => 'heroicon-o-user-group',
                        'url' => route('filament.admin.resources.roles.index'),
                        'color' => 'info',
                    ],
                    [
                        'title' => __('ledger.permission'),
                        'icon' => 'heroicon-o-key',
                        'url' => route('filament.admin.resources.permissions.index'),
                        'color' => 'info',
                    ],
                ],
            ],
            [
                'title' => __('ledger.settings.contents'),
                'icon' => 'heroicon-o-cog-6-tooth',
                'links' => [
                    [
                        'title' => __('ledger.tenant'),
                        'icon' => 'heroicon-o-building-office',
                        'url' => route('filament.admin.resources.tenants.index'),
                        'color' => 'success',
                    ],
                    [
                        'title' => __('ledger.technical_term'),
                        'icon' => 'heroicon-o-academic-cap',
                        'url' => route('filament.admin.resources.synonym.technical-term-groups.index'),
                        'color' => 'success',
                    ],
/*                    [
                        'title' => __('ledger.settings.folder'),
                        'icon' => 'heroicon-o-folder',
                        'url' => route('filament.admin.resources.folders.index'),
                        'color' => 'success',
                    ],*/
                    [
                        'title' => __('ledger.tags'),
                        'icon' => 'heroicon-o-tag',
                        'url' => route('filament.admin.resources.tags.index'),
                        'color' => 'success',
                    ],
                    [
                        'title' => __('ledger.settings.auto_link'), // ToDo: Add translation
                        'icon' => 'heroicon-o-link',
                        'url' => route('filament.admin.resources.auto-links.index'),
                        'color' => 'info',
                    ],
                ],
            ],
        ];

        return array_merge($groups, $centralGroups);
    }
}