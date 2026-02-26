<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Filament\Traits\HasPermissionMetadata;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    public static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->email,
        ];
    }

    use HasPermissionMetadata;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('ledger.user');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.user');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.user');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('user.user_details')) // セクション追加
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true) // ユニーク制約を追加 (編集時も考慮)
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('email_verified_at'),
                    ])->columns(2), // 2カラムレイアウト

                Forms\Components\Section::make(__('user.password_settings')) // セクション追加
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->label(__('user.password'))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->same('passwordConfirmation')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state)),
                        Forms\Components\TextInput::make('passwordConfirmation')
                            ->password()
                            ->label(__('user.password_confirmation'))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->dehydrated(false),
                    ])->columns(2), // 2カラムレイアウト

                Forms\Components\Section::make(__('user.roles_and_permissions')) // セクション追加
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->label(__('role.roles')) // ラベルを翻訳キーに
                            ->preload() // preload を追加
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.' ('.$record->guard_name.')'), // ラベル表示調整
                        // ->afterStateUpdated(...) // afterStateUpdated は組織との関連が複雑なため、一旦コメントアウト or 別途実装検討
                        // ->dehydrated(false), // Role の保存は RelationManager で行う方がシンプルかもしれない

                        // --- ここから Direct Permissions Select を追加 ---
                        Forms\Components\Select::make('permissions')
                            ->multiple()
                            ->relationship('permissions', 'name') // リレーションシップ名を指定
                            ->label(__('permission.direct_permissions')) // ラベルを設定
                            ->helperText(__('permission.direct_permissions_help')) // ヘルパーテキストを追加
                            ->preload()
                            // --- グループ化と翻訳のためのカスタマイズ ---
                            ->options(fn () => self::getPermissionOptions()),
                        // --- ここまで Direct Permissions Select ---
                    ])->columns(1), // 1カラムレイアウト (Selectを縦に並べる)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                // 組織名でのグローバル検索を有効にするための非表示カラム
                Tables\Columns\TextColumn::make('organizations.name')
                    ->label('Organization') // ラベルは必須だが表示はされない
                    ->searchable()
                    ->hidden(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ad_last_synced_at')
                    ->label(__('ledger.ad_last_synced_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ignore_ad_org_sync_until')
                    ->label(__('ledger.ignore_ad_org_sync_until'))
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state && \Illuminate\Support\Carbon::parse($state)->isPast() ? 'danger' : 'success')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('manual_sync_reason')
                    ->label(__('ledger.manual_sync_reason'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ViewColumn::make('combined_roles_permissions')
                    ->label(__('role.combined_roles_and_permissions')) // ラベルを翻訳キーに変更
                    ->view('filament.tables.columns.user-combined-roles-permissions'),
                Tables\Columns\TextColumn::make('primary_organization')
                    ->label(__('ledger.organizations.primary')) // ラベルを翻訳キーに変更
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $primaryOrganization = $record->PrimaryOrganization();
                        if ($primaryOrganization) {
                            // .name を .full_name に変更してフルパスを取得
                            return $primaryOrganization->full_name;
                        }

                        // 主所属がない場合のテキスト
                        return __('ledger.no_primary_organization');
                    })
                    ->color(fn ($state) => $state === __('ledger.no_primary_organization') ? 'gray' : 'primary'),

                Tables\Columns\TextColumn::make('organizations')
                    ->label(__('ledger.organization_section_title')) // ラベルを翻訳キーに変更
                    ->badge()
                    // ->organizations->pluck('full_name') で全所属組織のフルパスを取得
                    ->getStateUsing(fn ($record) => $record->organizations->pluck('full_name'))
                    ->color('info')
                    ->separator(' ') // バッジ間の区切り文字
                    ->wrap(), // 折り返しを有効に
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\Filter::make('manual_sync_status')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label(__('ledger.manual_sync_status_label'))
                            ->options([
                                'active' => __('ledger.manual_sync_status.active'),
                                'expired' => __('ledger.manual_sync_status.expired'),
                                'none' => __('ledger.manual_sync_status.none'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['status'] === 'active',
                                fn (Builder $query) => $query->where('ignore_ad_org_sync_until', '>', now())
                            )
                            ->when(
                                $data['status'] === 'expired',
                                fn (Builder $query) => $query->where('ignore_ad_org_sync_until', '<=', now())
                            )
                            ->when(
                                $data['status'] === 'none',
                                fn (Builder $query) => $query->whereNull('ignore_ad_org_sync_until')
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),

                // Bulk action: extend manual AD sync protection
                Tables\Actions\BulkAction::make('extendManualSync')
                    ->label(__('Extend Manual Sync'))
                    ->form([
                        Forms\Components\Textarea::make('manual_sync_reason')
                            ->label(__('Manual Sync Reason'))
                            ->rows(3)
                            ->placeholder(__('Optional reason')),
                    ])
                    ->action(function ($records, $data) {
                        $days = config('ldap_sync.manual_sync_extension_days', 90);
                        $until = now()->addDays($days);

                        $records->each(function ($record) use ($until, $data) {
                            $record->update([
                                'ignore_ad_org_sync_until' => $until,
                                'manual_sync_reason' => $data['manual_sync_reason'] ?? null,
                            ]);
                        });

                        $this->notify('success', __('Manual sync protection extended for selected users.'));
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrganizationRelationManager::class,
            RelationManagers\TokensRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['roles', 'permissions', 'organizations.roles', 'organizations.permissions']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('viewAny', User::class);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', User::class);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update', $record);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('delete', User::class);
    }
}
