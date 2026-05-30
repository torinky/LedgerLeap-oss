<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAnnouncementResource\Pages;
use App\Models\AdminAnnouncement;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament resource for managing admin announcement banners.
 *
 * Provides CRUD operations for announcement banners with status-based
 * badge display (published/scheduled/draft/ended/archived), scope
 * formatting, duplicate/replicate support, and permission-gated
 * access. Requires create/update/delete_admin_announcements permissions.
 */
class AdminAnnouncementResource extends Resource
{
    protected static ?string $model = AdminAnnouncement::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    public static function getLabel(): string
    {
        return __('ledger.admin_announcement_banner_title');
    }

    public static function getModelLabel(): string
    {
        return trim(__('ledger.admin_announcement_banner_title'));
    }

    public static function getPluralLabel(): string
    {
        return sprintf('%s', __('ledger.admin_announcement_banner_title'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(['xl' => 2])
                ->columnSpanFull()
                ->schema([
                    Section::make(__('ledger.admin_announcement_banner_form_title'))
                        ->description(__('ledger.admin_announcement_banner_form_hint'))
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Select::make('status')
                                        ->label(__('ledger.admin_announcement_banner_status_label'))
                                        ->beforeLabel(Icon::make('heroicon-o-shield-check')->size('sm'))
                                        ->default('draft')
                                        ->options(self::statusDisplayOptions())
                                        ->disabled()
                                        ->helperText(__('ledger.admin_announcement_banner_status_hint'))
                                        ->columnSpanFull(),
                                    TextInput::make('title')
                                        ->label(__('ledger.admin_announcement_banner_field_title'))
                                        ->beforeLabel(Icon::make('heroicon-o-megaphone')->size('sm'))
                                        ->live(onBlur: true)
                                        ->required()
                                        ->maxLength(120)
                                        ->autofocus()
                                        ->columnSpanFull(),
                                    Textarea::make('body')
                                        ->label(__('ledger.message'))
                                        ->beforeLabel(Icon::make('heroicon-o-document-text')->size('sm'))
                                        ->live(onBlur: true)
                                        ->helperText(__('ledger.admin_announcement_banner_body_hint'))
                                        ->required()
                                        ->rows(6)
                                        ->columnSpanFull(),
                                    Select::make('level')
                                        ->label(__('ledger.admin_announcement_banner_level_label'))
                                        ->beforeLabel(Icon::make('heroicon-o-exclamation-triangle')->size('sm'))
                                        ->live()
                                        ->afterStateUpdated(function (?string $state, callable $set): void {
                                            if ($state === 'critical') {
                                                $set('sticky', true);
                                            }
                                        })
                                        ->options(self::levelOptions())
                                        ->native(false)
                                        ->required(),
                                    CheckboxList::make('scope')
                                        ->label(__('ledger.admin_announcement_banner_publish_scope'))
                                        ->beforeLabel(Icon::make('heroicon-o-squares-2x2')->size('sm'))
                                        ->live()
                                        ->options(self::scopeOptions())
                                        ->columns(2)
                                        ->default(['current_tenant'])
                                        ->helperText(__('ledger.admin_announcement_banner_scope_hint'))
                                        ->required(),
                                    Toggle::make('sticky')
                                        ->label(__('ledger.admin_announcement_banner_sticky_label'))
                                        ->beforeLabel(Icon::make('heroicon-o-bookmark')->size('sm'))
                                        ->live()
                                        ->helperText(__('ledger.admin_announcement_banner_sticky_helper'))
                                        ->columnSpanFull(),
                                    DateTimePicker::make('starts_at')
                                        ->label(__('ledger.admin_announcement_banner_starts_at'))
                                        ->beforeLabel(Icon::make('heroicon-o-play')->size('sm'))
                                        ->live()
                                        ->required()
                                        ->seconds(false),
                                    DateTimePicker::make('ends_at')
                                        ->label(__('ledger.admin_announcement_banner_ends_at'))
                                        ->beforeLabel(Icon::make('heroicon-o-stop')->size('sm'))
                                        ->live()
                                        ->required()
                                        ->afterOrEqual('starts_at')
                                        ->seconds(false),
                                    TextInput::make('priority')
                                        ->label(__('ledger.default_sort_order'))
                                        ->numeric()
                                        ->default(0),
                                    TextInput::make('cta_label')
                                        ->label(__('ledger.admin_announcement_banner_cta_label'))
                                        ->beforeLabel(Icon::make('heroicon-o-link')->size('sm'))
                                        ->live(onBlur: true)
                                        ->maxLength(80),
                                    TextInput::make('cta_url')
                                        ->label(__('ledger.admin_announcement_banner_cta_url'))
                                        ->beforeLabel(Icon::make('heroicon-o-arrow-top-right-on-square')->size('sm'))
                                        ->live(onBlur: true)
                                        ->url()
                                        ->placeholder('https://')
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->columnSpan(['xl' => 1]),

                    Section::make(__('ledger.admin_announcement_banner_preview_title'))
                        ->description(__('ledger.admin_announcement_banner_preview_hint'))
                        ->schema([
                            View::make('filament.pages.admin-announcement-banner-preview-reset')
                                ->viewData(fn (): array => [
                                    'label' => __('ledger.admin_announcement_banner_preview_reset'),
                                ]),
                            View::make('components.admin.announcement-banner-preview-scoped')
                                ->viewData(fn (Get $get): array => [
                                    'announcement' => self::previewAnnouncement($get),
                                ]),
                        ])
                        ->columnSpan(['xl' => 1]),
                ]),
        ]);
    }

    public static function previewAnnouncement(Get $get): array
    {
        return [
            'title' => $get('title') ?? '',
            'body' => $get('body') ?? '',
            'level' => $get('level') ?? 'info',
            'sticky' => (bool) $get('sticky'),
            'scope' => self::normalizeScopeSelection($get('scope')),
            'published_at' => self::formatPreviewDateTime($get('starts_at')),
            'starts_at' => self::formatPreviewDateTime($get('starts_at')),
            'ends_at' => self::formatPreviewDateTime($get('ends_at')),
            'links' => array_filter([
                filled($get('cta_label')) && filled($get('cta_url'))
                    ? [
                        'label' => $get('cta_label'),
                        'url' => $get('cta_url'),
                    ]
                    : null,
            ]),
            'dismiss_storage_key' => self::previewDismissStorageKey($get),
        ];
    }

    protected static function previewDismissStorageKey(Get $get): string
    {
        $parts = [
            $get('title') ?? '',
            $get('body') ?? '',
            $get('level') ?? '',
            json_encode(self::normalizeScopeSelection($get('scope')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string) ((bool) $get('sticky') ? 1 : 0),
            $get('starts_at') ?? '',
            $get('ends_at') ?? '',
            $get('cta_label') ?? '',
            $get('cta_url') ?? '',
        ];

        return 'ledgerleap.admin_announcement_banner.preview:'.sha1(implode('|', $parts));
    }

    protected static function formatPreviewDateTime(?string $value): string
    {
        if (! filled($value)) {
            return __('ledger.none');
        }

        return CarbonImmutable::parse((string) $value)->format('Y/m/d H:i');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ledger.admin_announcement_banner_status_label'))
                    ->badge()
                    ->color(function (AdminAnnouncement $record): string {
                        return self::statusDisplayColor($record->displayStatusKey());
                    })
                    ->icon(function (AdminAnnouncement $record): string {
                        return self::statusDisplayIcon($record->displayStatusKey());
                    })
                    ->formatStateUsing(function (AdminAnnouncement $record): string {
                        return self::statusDisplayLabel($record->displayStatusKey());
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('ledger.admin_announcement_banner_field_title'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('level')
                    ->label(__('ledger.admin_announcement_banner_level_label'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('ledger.'.$state)),
                Tables\Columns\TextColumn::make('scope')
                    ->label(__('ledger.admin_announcement_banner_publish_scope'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::formatScopeDisplay($state)),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('ledger.admin_announcement_banner_starts_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('ledger.admin_announcement_banner_ends_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('ledger.updated_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('ledger.admin_announcement_banner_creator_label'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('modifier.name')
                    ->label(__('ledger.admin_announcement_banner_modifier_label'))
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([])
            ->recordActions([
                Actions\EditAction::make()
                    ->visible(fn (AdminAnnouncement $record): bool => self::canEdit($record)),
                Actions\ReplicateAction::make()
                    ->label(__('actions.duplicate'))
                    ->excludeAttributes([
                        'creator_id',
                        'modifier_id',
                        'status',
                        'published_at',
                    ])
                    ->beforeReplicaSaved(function (AdminAnnouncement $replica): void {
                        $replica->status = 'draft';
                        $replica->published_at = null;
                    }),
                Actions\DeleteAction::make()
                    ->visible(fn (AdminAnnouncement $record): bool => self::canDelete($record)),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => self::canDeleteAny()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminAnnouncements::route('/'),
            'create' => Pages\CreateAdminAnnouncement::route('/create'),
            'edit' => Pages\EditAdminAnnouncement::route('/{record}/edit'),
        ];
    }

    public static function levelOptions(): array
    {
        return [
            'info' => __('ledger.info'),
            'warning' => __('ledger.warning'),
            'critical' => __('ledger.critical'),
        ];
    }

    public static function scopeOptions(): array
    {
        return [
            'current_tenant' => self::currentTenantLabel(),
            'all_tenants' => __('ledger.admin_announcement_banner_scope_all_tenants'),
        ];
    }

    public static function currentTenantLabel(): string
    {
        $fromTenantId = session('filament_from_tenant_id');

        if ($fromTenantId && $tenant = Tenant::find($fromTenantId)) {
            return $tenant->name ?: $tenant->id;
        }

        if ($tenant = tenant()) {
            return $tenant->name ?: $tenant->id;
        }

        return __('ledger.current_tenant');
    }

    public static function normalizeScopeSelection(mixed $scope): array
    {
        if (! is_array($scope)) {
            $scope = filled($scope) ? [(string) $scope] : [];
        }

        return array_values(array_filter($scope, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    public static function scopeDisplayLabels(mixed $scope): array
    {
        return collect(self::normalizeScopeSelection($scope))
            ->map(fn (string $value): string => self::scopeOptions()[$value] ?? $value)
            ->values()
            ->all();
    }

    public static function formatScopeDisplay(mixed $scope): string
    {
        $labels = self::scopeDisplayLabels($scope);

        return $labels === [] ? __('ledger.none') : implode(' / ', $labels);
    }

    public static function statusDisplayOptions(): array
    {
        return [
            'draft' => __('ledger.admin_announcement_banner_status_draft'),
            'published' => __('ledger.admin_announcement_banner_status_published'),
            'scheduled' => __('ledger.admin_announcement_banner_status_scheduled'),
            'ended' => __('ledger.admin_announcement_banner_status_ended'),
            'archived' => __('ledger.admin_announcement_banner_status_archived'),
        ];
    }

    public static function statusDisplayLabel(string $state): string
    {
        return self::statusDisplayOptions()[$state] ?? $state;
    }

    public static function statusDisplayColor(string $state): string
    {
        return match ($state) {
            'published' => 'success',
            'scheduled' => 'info',
            'draft' => 'gray',
            'ended' => 'secondary',
            'archived' => 'warning',
            default => 'secondary',
        };
    }

    public static function statusDisplayIcon(string $state): string
    {
        return match ($state) {
            'published' => 'heroicon-o-check-circle',
            'scheduled' => 'heroicon-o-clock',
            'draft' => 'heroicon-o-pencil-square',
            'ended' => 'heroicon-o-archive-box',
            'archived' => 'heroicon-o-archive-box',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Converts CTA label and URL fields into the links payload array.
     *
     * Returns a single-element array with label/url when both fields
     * are filled; returns an empty array otherwise.
     *
     * @param  array  $data  Form data containing cta_label and cta_url
     * @return array
     */
    public static function toLinksPayload(array $data): array
    {
        return array_values(array_filter([
            filled($data['cta_label'] ?? null) && filled($data['cta_url'] ?? null)
                ? [
                    'label' => $data['cta_label'],
                    'url' => $data['cta_url'],
                ]
                : null,
        ]));
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('create_admin_announcements')
            || $user->can('update_admin_announcements')
            || $user->can('delete_admin_announcements');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_admin_announcements') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update_admin_announcements') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete_admin_announcements') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('delete_admin_announcements') ?? false;
    }
}
