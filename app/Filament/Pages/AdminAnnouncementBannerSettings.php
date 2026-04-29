<?php

namespace App\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    public int $previewResetNonce = 0;

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

    public function resetPreviewBanner(): void
    {
        $this->previewResetNonce++;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
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
                                            ->disabled()
                                            ->options($this->statusOptions())
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
                                            ->options($this->levelOptions())
                                            ->native(false)
                                            ->required(),

                                        Select::make('scope')
                                            ->label(__('ledger.admin_announcement_banner_publish_scope'))
                                            ->beforeLabel(Icon::make('heroicon-o-squares-2x2')->size('sm'))
                                            ->live()
                                            ->options($this->scopeOptions())
                                            ->native(false)
                                            ->required(),

                                        Toggle::make('sticky')
                                            ->label(__('ledger.admin_announcement_banner_sticky_label'))
                                            ->beforeLabel(Icon::make('heroicon-o-bookmark')->size('sm'))
                                            ->live()
                                            ->disabled(fn (Get $get): bool => $get('level') === 'critical')
                                            ->helperText(__('ledger.admin_announcement_banner_sticky_helper'))
                                            ->columnSpanFull(),

                                        DateTimePicker::make('starts_at')
                                            ->label(__('ledger.admin_announcement_banner_starts_at'))
                                            ->beforeLabel(Icon::make('heroicon-o-play')->size('sm'))
                                            ->live()
                                            ->seconds(false),

                                        DateTimePicker::make('ends_at')
                                            ->label(__('ledger.admin_announcement_banner_ends_at'))
                                            ->beforeLabel(Icon::make('heroicon-o-stop')->size('sm'))
                                            ->live()
                                            ->seconds(false),

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
            'dismiss_storage_key' => $this->previewDismissStorageKey($get),
        ];
    }

    protected function previewDismissStorageKey(Get $get): string
    {
        $parts = [
            $get('title') ?? '',
            $get('body') ?? '',
            $get('level') ?? '',
            $get('scope') ?? '',
            (string) ((bool) $get('sticky') ? 1 : 0),
            $get('starts_at') ?? '',
            $get('ends_at') ?? '',
            $get('cta_label') ?? '',
            $get('cta_url') ?? '',
            (string) $this->previewResetNonce,
        ];

        return 'ledgerleap.admin_announcement_banner.preview:' . sha1(implode('|', $parts));
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
            'status' => 'draft',
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

    protected function getHeaderActions(): array
    {
        $fromTenantId = session('filament_from_tenant_id');

        return [
            Action::make('backToList')
                ->label(__('ledger.back_to_list'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn (): string => AdminAnnouncementBannerIndex::getUrl().($fromTenantId ? '?tenant='.$fromTenantId : '')),
            Action::make('saveDraft')
                ->label(__('ledger.admin_announcement_banner_save_draft_action'))
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->action('saveDraft'),
            Action::make('publishAnnouncement')
                ->label(__('ledger.admin_announcement_banner_publish_action'))
                ->icon('heroicon-o-megaphone')
                ->color('success')
                ->action('publishAnnouncement'),
            Action::make('archiveAnnouncement')
                ->label(__('ledger.admin_announcement_banner_archive_action'))
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->action('archiveAnnouncement'),
        ];
    }

    public function saveDraft(): void
    {
        $this->setAnnouncementStatus('draft');

        Notification::make()
            ->title(__('ledger.success'))
            ->body(__('ledger.draft_saved'))
            ->success()
            ->send();
    }

    public function publishAnnouncement(): void
    {
        $this->validatePublishingDraft();
        $this->setAnnouncementStatus('published');

        Notification::make()
            ->title(__('ledger.success'))
            ->body(__('ledger.admin_announcement_banner_published'))
            ->success()
            ->send();
    }

    public function archiveAnnouncement(): void
    {
        $this->setAnnouncementStatus('archived');

        Notification::make()
            ->title(__('ledger.success'))
            ->body(__('ledger.admin_announcement_banner_archived'))
            ->success()
            ->send();
    }

    public function statusOptions(): array
    {
        return [
            'draft' => __('ledger.admin_announcement_banner_status_draft'),
            'published' => __('ledger.admin_announcement_banner_status_published'),
            'archived' => __('ledger.admin_announcement_banner_status_archived'),
        ];
    }

    protected function setAnnouncementStatus(string $status): void
    {
        $data = array_merge($this->data, ['status' => $status]);

        if ($status === 'published' && ($data['level'] ?? null) === 'critical') {
            $data['sticky'] = true;
        }

        $this->form->fill($data);
    }

    protected function validatePublishingDraft(): void
    {
        Validator::make(
            $this->data,
            [
                'status' => ['required', Rule::in(array_keys($this->statusOptions()))],
                'title' => ['required', 'string', 'max:120'],
                'body' => ['required', 'string'],
                'level' => ['required', Rule::in(array_keys($this->levelOptions()))],
                'scope' => ['required', Rule::in(array_keys($this->scopeOptions()))],
                'starts_at' => ['required', 'date_format:Y-m-d H:i:s'],
                'ends_at' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:starts_at'],
                'cta_label' => ['nullable', 'string', 'max:80'],
                'cta_url' => ['nullable', 'url'],
            ],
            [
                'status.required' => __('ledger.admin_announcement_banner_validation_status_required'),
                'title.required' => __('ledger.admin_announcement_banner_validation_title_required'),
                'body.required' => __('ledger.admin_announcement_banner_validation_body_required'),
                'level.required' => __('ledger.admin_announcement_banner_validation_level_required'),
                'scope.required' => __('ledger.admin_announcement_banner_validation_scope_required'),
                'starts_at.required' => __('ledger.admin_announcement_banner_validation_starts_at_required'),
                'ends_at.required' => __('ledger.admin_announcement_banner_validation_ends_at_required'),
                'ends_at.after_or_equal' => __('ledger.admin_announcement_banner_validation_ends_at_after_or_equal'),
                'cta_url.url' => __('ledger.admin_announcement_banner_validation_cta_url'),
            ],
        )->validate();
    }
}