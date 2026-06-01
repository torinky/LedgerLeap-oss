<?php

namespace App\Filament\Resources;

use App\Enums\FolderPermissionType;
use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers\FolderPermissionRelationManager;
use App\Filament\Resources\RoleResource\RelationManagers\NotificationSettingsRelationManager;
use App\Filament\Resources\RoleResource\RelationManagers\OrganizationRelationManager;
use App\Filament\Resources\RoleResource\RelationManagers\UserRelationManager;
use App\Filament\Traits\HasPermissionMetadata;
use App\Models\Folder;
use App\Models\NotificationType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament resource for managing roles and their permissions.
 *
 * Provides CRUD for roles, system permission assignment, folder permission
 * management, organization scoping, user assignment, and notification settings.
 */
class RoleResource extends Resource
{
    use HasPermissionMetadata;

    protected static ?string $model = Role::class;

    public static bool $shouldRegisterNavigation = false;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Guard' => $record->guard_name,
        ];
    }

    public static function getLabel(): string
    {
        return __('ledger.settings.roles');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.settings.roles');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.settings.roles');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('filament-spatie-roles-permissions.navigation_section_group', 'ledger.setting'));
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-spatie-roles-permissions.sort.role_navigation', 1);
    }

    public static function form(Schema $schema): Schema
    {
        // --- 既存の parentForm と components の取得は不要になる ---
        // $parentForm = parent::form($form);
        // $components = $parentForm->getComponents();

        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('role.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Role::class, 'name', ignoreRecord: true),
                                TextInput::make('abbreviation')
                                    ->label(__('role.abbreviation'))
                                    ->placeholder(__('role.abbreviation_placeholder'))
                                    ->maxLength(255),
                                Select::make('guard_name')
                                    ->label(__('role.guard_name'))
                                    ->options(config('filament-spatie-roles-permissions.guard_names'))
                                    ->default(config('filament-spatie-roles-permissions.default_guard_name'))
                                    ->visible(fn () => config('filament-spatie-roles-permissions.should_show_guard', true))
                                    ->required(),

                                // --- Permissions Select の変更 ---
                                Select::make('permissions')
                                    ->columnSpanFull()
                                    ->label(__('role.permissions'))
                                    ->relationship(
                                        name: 'permissions',
                                        // titleAttribute: 'name', // titleAttribute の代わりに getOptionLabelFromRecordUsing を使用
                                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'),
                                    )
                                    ->multiple()
                                    ->preload(config('filament-spatie-roles-permissions.preload_permissions'))
                                    ->searchable(['name']) // name のみで検索可能にする（必要なら guard_name も）
                                    // --- ここから グループ化と翻訳のためのカスタマイズ ---
                                    ->getOptionLabelFromRecordUsing(function (Model $record) {
                                        $group = self::getPermissionGroup($record->name);
                                        $groupLabel = $group ? (__('permission.group.'.$group).' - ') : ''; // グループ名をプレフィックスに

                                        return $groupLabel.__('permission.name.'.$record->name);
                                        // return __('permission.name.' . $record->name) . ' (' . $record->guard_name . ')'; // 元の表示
                                    })
                                    // オプションをグループごとにソートする (任意)
                                    ->options(fn () => self::getPermissionOptions()),
                                // --- ここまで グループ化と翻訳のためのカスタマイズ ---

                                Select::make('global_notify') // グローバル通知のチェックボックス
                                // --- 変更なし ---
                                    ->label(__('role.global_notify'))
                                    ->options(function () {
                                        return NotificationType::where('folder_relation', null)->pluck('name', 'id')
                                            ->mapWithKeys(function ($folderPermission, $key) {
                                                //                                                dd($folderPermission,$key);
                                                return [$key => __('ledger.notification_types.'.$folderPermission)];
                                            })
                                            ->toArray();
                                    })
                                    ->afterStateHydrated(function ($component, ?Role $record) {
                                        if (! $record) {
                                            return;
                                        } // 新規作成時は処理しない
                                        $globalNotifyTypes = NotificationType::where('folder_relation', null)->pluck('id')->toArray();
                                        $hasGlobalNotify = RoleFolderPermission::where('role_id', $record->id)
                                            ->where('folder_id', 1) // ルートフォルダーのID
                                            ->whereIn('notification_type_id', $globalNotifyTypes)
                                            ->where('permission', FolderPermissionType::NOTIFY_ON)
                                            ->pluck('notification_type_id')
                                            ->toArray();
                                        $component->state($hasGlobalNotify);
                                    })
                                    ->dehydrateStateUsing(function ($component, ?Role $record, $state) {
                                        // 新規作成時は record が null になるため、保存処理をスキップする
                                        // グローバル通知設定はロール編集時のみ有効（docs/function/Role.md 参照）
                                        if (! $record) {
                                            return null;
                                        }

                                        $globalNotifyTypes = NotificationType::where('folder_relation', null)->pluck('id')->toArray();
                                        // state が null (未選択) の場合は空配列として扱う
                                        $selectedTypes = $state ?? [];

                                        // 削除された通知タイプをNOTIFY_OFFにする
                                        RoleFolderPermission::where('role_id', $record->id)
                                            ->where('folder_id', 1)
                                            ->whereIn('notification_type_id', $globalNotifyTypes)
                                            ->whereNotIn('notification_type_id', $selectedTypes)
                                            ->update(['permission' => FolderPermissionType::NOTIFY_OFF, 'modifier_id' => auth()->id()]);

                                        // 選択された通知タイプをNOTIFY_ONにする
                                        foreach ($selectedTypes as $globalNotifyTypeId) {
                                            RoleFolderPermission::updateOrCreate(
                                                ['role_id' => $record->id, 'folder_id' => 1, 'notification_type_id' => $globalNotifyTypeId],
                                                ['permission' => FolderPermissionType::NOTIFY_ON, 'modifier_id' => auth()->id()]
                                            );
                                        }

                                        // dehydrateStateUsing は状態を保存するだけなので、null を返すか何もしない
                                        return null; // この Select の状態自体は保存しない
                                    })
                                    ->multiple()
                                    ->columnSpanFull(),

                                TextInput::make('description')
                                    ->label(__('ledger.description'))
                                    ->placeholder('Enter a description...')
                                    ->columnSpanFull(),

                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $allFolderTree = Folder::get()->toTree();
        $allFolderList = Folder::treeList($allFolderTree);

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('role.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('folders')
                    ->label(__('ledger.folder.scoped'))
                    ->getStateUsing(function ($record): array {
                        //                        dd($record);
                        // フォルダー権限とフォルダー情報を一緒に取得
                        return $record->accessibleFolders()
                            ->get()
                            ->map(function ($folderPermission) {
                                return [
                                    'permission' => $folderPermission->pivot->permission,
                                    'folder_title' => $folderPermission->title ?? '不明なフォルダー',
                                ];
                            })
                            ->toArray();
                    })
                    ->badge()
                    ->color(function (array $state): string {
                        // 権限に応じて色を返す
                        return $state['permission']->getColor();
                    })
                    ->formatStateUsing(function (array $state): string {
                        return "{$state['folder_title']}: {$state['permission']->getLabel()}";
                    })
                    ->separator(' ') // バッジ間の区切り文字
                    ->wrap()         // 長い場合は折り返し
                    ->translateLabel(),
                TextColumn::make('organizations.name')
                    ->label(__('ledger.organizations.scoped'))
                    ->badge(),
                TextColumn::make('permissions.name')
                    ->label(__('ledger.settings.permissions'))
                    ->badge(),
                TextColumn::make('permissions.name')
                    ->label(__('role.permissions'))
                    ->formatStateUsing(function (string $state): string {
                        return __('permission.name.'.$state);
                    })
                    ->badge(),

                TextColumn::make('guard_name')
                    ->label(__('role.guard_name'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('ledger.description'))
                    ->sortable()
                    ->searchable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //            PermissionRelationManager::class,
            OrganizationRelationManager::class,
            UserRelationManager::class,
            FolderPermissionRelationManager::class,
            NotificationSettingsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('tags');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('viewAny', Role::class);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', Role::class);
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
        return auth()->user()->can('delete', Role::class);
    }
}
