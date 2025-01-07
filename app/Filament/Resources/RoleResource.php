<?php

namespace App\Filament\Resources;

use Althinect\FilamentSpatieRolesPermissions\Resources\RoleResource as BaseRoleResource;
use Althinect\FilamentSpatieRolesPermissions\Resources\RoleResource\RelationManager\PermissionRelationManager;
use App\Filament\Resources\OrganizationResource\RelationManagers\UserRelationManager;
use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers\ReadableFolderRelationManager;
use App\Filament\Resources\RoleResource\RelationManagers\OrganizationRelationManager;
use App\Models\Folder;
use App\Models\Role;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoleResource extends BaseRoleResource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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

    public static function form(Form $form): Form
    {
        $parentForm = parent::form($form);
        $components = $parentForm->getComponents();

        return $form
            ->schema([
                Section::make()
                    ->schema([
                        ...$components,

                        Select::make('readable folders')
                            ->label(__('ledger.folder.readable'))
                            ->options(function () {
                                return Folder::treeList(Folder::get()->toTree());
                            })
                            ->multiple()
                            ->afterStateHydrated(function (Select $component, $state, ?Model $record) {
                                if ($record) {
                                    $folderIds = $record->readableFolders()->pluck('folders.id')->toArray();
                                    $component->state($folderIds);
                                }
                            })
                            ->dehydrated(false),

                        Select::make('writable folders')
                            ->label(__('ledger.folder.writable'))
                            ->options(function () {
                                return Folder::treeList(Folder::get()->toTree());
                            })
                            ->multiple()
                            ->afterStateHydrated(function (Select $component, $state, ?Model $record) {
                                if ($record) {
                                    $folderIds = $record->writableFolders()->pluck('folders.id')->toArray();
                                    $component->state($folderIds);
                                }
                            })
                            ->dehydrated(false),

                        TextInput::make('description')
                            ->label(__('ledger.description'))
                            ->placeholder('Enter a description...'),

                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $parentTable = parent::table($table);
        $columns = $parentTable->getColumns();

        $allFolderTree = Folder::get()->toTree();
        $allFolderList = Folder::treeList($allFolderTree);

        return $table
            ->columns([
                ...$columns,
                Tables\Columns\TextColumn::make('folders')
                    ->label(__('ledger.folder.scoped'))
                    ->getStateUsing(function ($record): array {
                        // フォルダー権限とフォルダー情報を一緒に取得
                        return $record->folderPermissions()
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
                        return match ($state['permission']) {
                            'read' => 'info',     // 青
                            'write' => 'warning', // 黄
                            'admin' => 'success', // 緑
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function (array $state): string {
                        // フォルダータイトルと権限を組み合わせて表示
                        $permission = match ($state['permission']) {
                            'read' => '閲覧',
                            'write' => '編集',
                            'admin' => '管理者',
                            default => '不明',
                        };

                        return "{$state['folder_title']}: {$permission}";
                    })
                    ->separator(' ') // バッジ間の区切り文字
                    ->wrap()         // 長い場合は折り返し
                    ->translateLabel(),
                Tables\Columns\TextColumn::make('organizations.name')
                    ->label(__('ledger.organizations.scoped'))
                    ->badge(),
                Tables\Columns\TextColumn::make('permissions.name')
                    ->label(__('ledger.settings.permissions'))
                    ->badge(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->badge(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('ledger.description'))
                    ->sortable()
                    ->searchable(),

            ])
            ->filters($parentTable->getFilters())
            ->actions($parentTable->getActions())
            ->bulkActions($parentTable->getBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            PermissionRelationManager::class,
            UserRelationManager::class,
            ReadableFolderRelationManager::class,
            OrganizationRelationManager::class,
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
}
