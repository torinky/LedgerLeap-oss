<?php

namespace App\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AdminAnnouncementBannerSettings extends \Filament\Pages\Page implements HasForms
{
    use InteractsWithForms;

    public static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.admin-announcement-banner-settings';

    protected static ?string $slug = 'admin-announcement-banner-settings';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->defaultDraft());
    }

    public function getTitle(): string|Htmlable
    {
        return __('ledger.admin_announcement_banner_title');
    }

    public function resetDraft(): void
    {
        $this->form->fill($this->defaultDraft());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['xl' => 2])
                    ->schema([
                        Section::make(__('ledger.admin_announcement_banner_form_title'))
                            ->description(__('ledger.admin_announcement_banner_form_hint'))
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('title')
                                            ->label(__('ledger.admin_announcement_banner_field_title'))
                                            ->beforeLabel(Icon::make('heroicon-o-megaphone')->size('sm'))
                                            ->required()
                                            ->maxLength(120)
                                            ->autofocus()
                                            ->columnSpanFull(),

                                        Textarea::make('body')
                                            ->label(__('ledger.message'))
                                            ->beforeLabel(Icon::make('heroicon-o-document-text')->size('sm'))
                                            ->helperText(__('ledger.admin_announcement_banner_body_hint'))
                                            ->required()
                                            ->rows(6)
                                            ->columnSpanFull(),

                                        Select::make('level')
                                            ->label(__('ledger.admin_announcement_banner_level_label'))
                                            ->beforeLabel(Icon::make('heroicon-o-exclamation-triangle')->size('sm'))
                                            ->options($this->levelOptions())
                                            ->native(false)
                                            ->required(),

                                        Select::make('scope')
                                            ->label(__('ledger.admin_announcement_banner_publish_scope'))
                                            ->beforeLabel(Icon::make('heroicon-o-squares-2x2')->size('sm'))
                                            ->options($this->scopeOptions())
                                            ->native(false)
                                            ->required(),

                                        Toggle::make('sticky')
                                            ->label(__('ledger.admin_announcement_banner_sticky_label'))
                                            ->beforeLabel(Icon::make('heroicon-o-bookmark')->size('sm'))
                                            ->helperText(__('ledger.admin_announcement_banner_sticky_helper'))
                                            ->columnSpanFull(),

                                        DateTimePicker::make('starts_at')
                                            ->label(__('ledger.admin_announcement_banner_starts_at'))
                                            ->beforeLabel(Icon::make('heroicon-o-play')->size('sm'))
                                            ->seconds(false),

                                        DateTimePicker::make('ends_at')
                                            ->label(__('ledger.admin_announcement_banner_ends_at'))
                                            ->beforeLabel(Icon::make('heroicon-o-stop')->size('sm'))
                                            ->seconds(false),

                                        TextInput::make('cta_label')
                                            ->label(__('ledger.admin_announcement_banner_cta_label'))
                                            ->beforeLabel(Icon::make('heroicon-o-link')->size('sm'))
                                            ->maxLength(80),

                                        TextInput::make('cta_url')
                                            ->label(__('ledger.admin_announcement_banner_cta_url'))
                                            ->beforeLabel(Icon::make('heroicon-o-arrow-top-right-on-square')->size('sm'))
                                            ->url()
                                            ->placeholder('https://')
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpan(['xl' => 1]),

                        Section::make(__('ledger.admin_announcement_banner_preview_title'))
                            ->description(__('ledger.admin_announcement_banner_preview_hint'))
                            ->schema([
                                View::make('components.admin.announcement-banner')
                                    ->viewData(fn (Get $get): array => [
                                        'announcement' => $this->previewAnnouncement($get),
                                    ]),
                            ])
                            ->columnSpan(['xl' => 1]),
                    ]),
            ]);
    }

    public function previewAnnouncement(Get $get): array
    {
        return [
            'title' => $get('title') ?? '',
            'body' => $get('body') ?? '',
            'level' => $get('level') ?? 'info',
            'sticky' => (bool) $get('sticky'),
            'scope' => $get('scope') ?? 'current_tenant',
            'published_at' => $this->formatPreviewDateTime($get('starts_at')),
            'starts_at' => $this->formatPreviewDateTime($get('starts_at')),
            'ends_at' => $this->formatPreviewDateTime($get('ends_at')),
            'links' => array_filter([
                filled($get('cta_label')) && filled($get('cta_url'))
                    ? [
                        'label' => $get('cta_label'),
                        'url' => $get('cta_url'),
                    ]
                    : null,
            ]),
            'dismiss_storage_key' => 'ledgerleap.admin_announcement_banner.preview',
        ];
    }

    public function levelOptions(): array
    {
        return [
            'info' => __('ledger.info'),
            'warning' => __('ledger.warning'),
            'critical' => __('ledger.critical'),
        ];
    }

    public function scopeOptions(): array
    {
        return [
            'current_tenant' => __('ledger.admin_announcement_banner_scope_current_tenant'),
            'all_tenants' => __('ledger.admin_announcement_banner_scope_all_tenants'),
        ];
    }

    public function stickyLabel(): string
    {
        return ($this->data['sticky'] ?? false)
            ? __('ledger.admin_announcement_banner_sticky_on')
            : __('ledger.admin_announcement_banner_sticky_off');
    }

    protected function defaultDraft(): array
    {
        $startsAt = CarbonImmutable::now();

        return [
            'title' => __('ledger.admin_announcement_banner_default_title'),
            'body' => __('ledger.admin_announcement_banner_default_body'),
            'level' => 'warning',
            'sticky' => false,
            'scope' => 'current_tenant',
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $startsAt->addDay()->format('Y-m-d H:i:s'),
            'cta_label' => __('ledger.open'),
            'cta_url' => '',
        ];
    }

    protected function formatPreviewDateTime(?string $value): string
    {
        if (! filled($value)) {
            return __('ledger.none');
        }

        return CarbonImmutable::parse((string) $value)->format('Y/m/d H:i');
    }
}