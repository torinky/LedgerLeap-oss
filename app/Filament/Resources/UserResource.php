<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    public static bool $shouldRegisterNavigation = false;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

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
                        ->required(fn(string $context): bool => $context === 'create')
                        ->minLength(8)
                        ->same('passwordConfirmation')
                        ->dehydrated(fn($state) => filled($state))
                        ->dehydrateStateUsing(fn($state) => Hash::make($state)),
                    Forms\Components\TextInput::make('passwordConfirmation')
                        ->password()
                        ->label(__('user.password_confirmation'))
                        ->required(fn(string $context): bool => $context === 'create')
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
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ' (' . $record->guard_name . ')'), // ラベル表示調整
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
                        ->options(function () {
                            $permissions = Permission::orderBy('name')->get();
                            $groupedPermissions = $permissions->groupBy(function ($permission) {
                                return self::getPermissionGroup($permission->name) ?? 'other';
                            })->sortBy(function ($group, $key) {
                                $order = [ // RoleResource と同じ順序
                                    'user' => 1, 'organization' => 2, 'role' => 3, 'permission' => 4,
                                    'folder' => 5, 'folder_permission' => 6,
                                    'ledger_define' => 7, 'ledger' => 8,
                                    'workflow_notification' => 9, 'notification' => 10,
                                    'activity_log' => 11,
                                    'other' => 99,
                                ];
                                return $order[$key] ?? 99;
                            });

                            $options = [];
                            foreach ($groupedPermissions as $groupKey => $permissionsInGroup) {
                                foreach ($permissionsInGroup as $permission) {
                                    $options[$permission->id] = self::getFormattedPermissionLabel($permission);
                                }
                            }
                            return $options;
                        }),
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
                Tables\Columns\ViewColumn::make('combined_roles_permissions')
                    ->label('Combined Roles & Permissions')
                    ->view('filament.tables.columns.user-combined-roles-permissions'),
                //                Tables\Columns\TextColumn::make('roles.name')->badge(),
                //                Tables\Columns\TextColumn::make('permissions.name')->badge(),
                Tables\Columns\TextColumn::make('primary_organization')
                    ->label('primary organization')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $primaryOrganization = $record->PrimaryOrganization();
                        if ($primaryOrganization) {
                            return $primaryOrganization->name;
                        }

                        return null;
                    })
                    ->colors(['primary'])
//                    ->searchable()
                ,
                Tables\Columns\TextColumn::make('organizations')
                    ->label('organizations')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->organizations()->pluck('name', 'is_primary'))
                    ->colors(['info'])
//                    ->searchable()
                ,
                /*                Tables\Columns\TextColumn::make('organizations')
                    ->formatStateUsing(function ($state, $record) {
                        $getBadgeHtml = function ($org) {
                            $style = $org->pivot->is_primary
                                ? '--c-50:var(--success-50);--c-400:var(--success-400);--c-600:var(--success-600);'
                                : '--c-50:var(--gray-50);--c-400:var(--gray-400);--c-600:var(--gray-600);';
                            $colorClass = 'fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30';
                            $label = e($org->name).($org->pivot->is_primary ? ' (Primary)' : '');

                            return "<span style='{$style}' class='fi-badge inline-flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 {$colorClass}'>{$label}</span>";
                        };

                        return new HtmlString(
                            $record->organizations->map($getBadgeHtml)->implode(' ')
                        );
                    })
                    ->html(),*/
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrganizationRelationManager::class,
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

    // --- 権限名からグループキーを判定するヘルパーメソッド ---
    protected static function getPermissionGroup(string $permissionName): ?string
    {
        if (Str::contains($permissionName, ['users'])) return 'user';
        if (Str::contains($permissionName, ['organizations'])) return 'organization';
        if (Str::contains($permissionName, ['roles'])) return 'role';
        if (Str::contains($permissionName, ['permissions'])) return 'permission'; // 'manage_permissions' など
        if (Str::contains($permissionName, ['folder_permissions'])) return 'folder_permission'; // フォルダー権限設定
        if (Str::contains($permissionName, ['ledgers']) && !Str::contains($permissionName, ['define'])) return 'ledger'; // 台帳操作
        if (Str::contains($permissionName, ['ledger_defines'])) return 'ledger_define'; // 台帳定義
        if (Str::contains($permissionName, ['folders']) && !Str::contains($permissionName, ['rolefolder'])) return 'folder'; // フォルダ管理
        if (Str::contains($permissionName, ['workflow', 'email'])) return 'workflow_notification'; // ワークフロー通知
        if (Str::contains($permissionName, ['notify'])) return 'notification'; // システム内通知
        if (Str::contains($permissionName, ['activity_logs'])) return 'activity_log'; // アクティビティログ
        return null; // グループが見つからない場合
    }

    // --- 整形された権限ラベルを取得するヘルパーメソッド ---
    protected static function getFormattedPermissionLabel(Permission $permission): string
    {
        $group = self::getPermissionGroup($permission->name);
        $groupLabel = $group ? (__('permission.group.' . $group) . ' - ') : '';
        return $groupLabel . __('permission.name.' . $permission->name);
    }

}
