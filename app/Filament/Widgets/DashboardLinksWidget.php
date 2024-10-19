<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardLinksWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-links-widget';

    protected int|string|array $columnSpan = [
        'sm' => 2,
        'md' => 3,
        'lg' => 4,
        'xl' => 6,
    ];

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
        return [
            [
                'title' => __('User Management'),
                'icon' => 'heroicon-o-users',
                'links' => [
                    [
                        'title' => __('Users'),
                        'icon' => 'heroicon-o-user',
                        'url' => route('filament.admin.resources.users.index'),
                        'color' => 'danger',
                    ],
                    [
                        'title' => __('Organizations'),
                        'icon' => 'heroicon-o-building-office',
                        'url' => route('filament.admin.resources.organizations.index'),
                        'color' => 'warning',
                    ],
                ],
            ],
            [
                'title' => __('Access Control'),
                'icon' => 'heroicon-o-shield-check',
                'links' => [
                    [
                        'title' => __('Roles'),
                        'icon' => 'heroicon-o-user-group',
                        'url' => route('filament.admin.resources.roles.index'),
                        'color' => 'info',
                    ],
                    [
                        'title' => __('Permissions'),
                        'icon' => 'heroicon-o-key',
                        'url' => route('filament.admin.resources.permissions.index'),
                        'color' => 'secondary',
                    ],
                ],
            ],
            [
                'title' => __('System Settings'),
                'icon' => 'heroicon-o-cog-6-tooth',
                'links' => [
                    [
                        'title' => __('Folders'),
                        'icon' => 'heroicon-o-folder',
                        'url' => route('filament.admin.resources.folders.index'),
                        'color' => 'success',
                    ],
                    [
                        'title' => __('ledger.general_settings'),
                        'icon' => 'heroicon-o-adjustments-horizontal',
                        'url' => route('ledgerDefine.index'),
                        'color' => 'primary',
                    ],
                    [
                        'title' => __('ledger.technical_term'),
                        'icon' => 'heroicon-o-academic-cap',
                        'url' => route('filament.admin.resources.synonym.technical-term-groups.index'),
                        'color' => 'success',
                    ],
                ],
            ],
        ];
    }
}
