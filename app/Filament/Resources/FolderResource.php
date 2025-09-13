<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FolderResource\Pages;
use App\Filament\Resources\FolderResource\RelationManagers;
use App\Models\Folder;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Routing\Route as RouteAlias;
use Illuminate\Support\Facades\Route;

class FolderResource extends Resource
{
    public static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Folder::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    public static function getLabel(): string
    {
        return __('ledger.folders');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.folders');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.folders');
    }

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Parent' => $record->parent ? $record->parent->title : 'None',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('ledger.basic_information'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('ledger.folder.title'))
                            ->required()
                            ->maxLength(255),
                        SelectTree::make('parent_id')
                            ->label(__('ledger.folder.parent'))
                            ->relationship('parent', 'title', 'parent_id')
                            // ->withCount() // 必要に応じて
                            ->enableBranchNode()
                            ->defaultOpenLevel(1), // 必要に応じて調整
                        Forms\Components\Select::make('creator_id')
                            ->label(__('ledger.creator.name'))
                            ->relationship('creator', 'name')
                            ->searchable()
                            ->required()
                            ->default(fn() => auth()->id())
                            ->disabledOn('edit'), // 編集時は無効化
                        Forms\Components\Select::make('modifier_id')
                            ->label(__('ledger.modifier.name'))
                            ->relationship('modifier', 'name')
                            ->searchable()
                            ->required()
                            ->dehydrated(true) // 常に送信する
                            ->visible(fn() => auth()->check()) // ログインユーザーのみ表示
                        ,
                    ])->columns(2),

                Section::make(__('ledger.workflow.required_roles_setting'))
                    ->description(__('ledger.workflow.required_roles_setting_helper'))
                    ->schema([
                        Select::make('requiredInspectorRoles') // リレーション名に合わせる
                        ->label(__('ledger.workflow.required_inspector_roles'))
                            ->relationship('requiredInspectorRoles', 'name') // リレーション名と表示カラム
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder(__('ledger.select_roles'))
                            ->helperText(__('ledger.workflow.required_inspector_roles_helper'))
                            ->saveRelationshipsUsing(static function (Model $record, $state) { // $state は選択されたロールIDの配列
                                if (is_array($state)) {
                                    $syncData = collect($state)->mapWithKeys(fn($roleId) => [$roleId => ['type' => 'inspector']])->all();
                                    // attachではなくsyncを使うのが一般的。既存の関連は解除され、新しいものだけが残る。
                                    // もし既存の関連を維持しつつ追加したい場合は、ロジックを調整する必要がある。
                                    $record->requiredInspectorRoles()->sync($syncData);
                                } else {
                                    // 何も選択されなかった場合は空でsync（全ての関連を解除）
                                    $record->requiredInspectorRoles()->sync([]);
                                }
                            })
                        ,
                        Select::make('requiredApproverRoles') // リレーション名に合わせる
                        ->label(__('ledger.workflow.required_approver_roles'))
                            ->relationship('requiredApproverRoles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder(__('ledger.select_roles'))
                            ->helperText(__('ledger.workflow.required_approver_roles_helper'))
                            ->saveRelationshipsUsing(static function (Model $record, $state) {
                                if (is_array($state)) {
                                    $syncData = collect($state)->mapWithKeys(fn($roleId) => [$roleId => ['type' => 'approver']])->all();
                                    $record->requiredApproverRoles()->sync($syncData);
                                } else {
                                    $record->requiredApproverRoles()->sync([]);
                                }
                            })
                        ,
                    ])->columns(2),

                // Forms\Components\Hidden::make('lft'), // NodeTrait が自動で処理するはず
                // Forms\Components\Hidden::make('rgt'),
                // Forms\Components\Hidden::make('lvl'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('フォルダ名')
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('title', 'like', "%{$search}%")),
                Tables\Columns\TextColumn::make('creator.name')->label('Creator')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('modifier.name')->label('Modifier')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('parent.title')->label('Parent Folder')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->getStateUsing(function ($record) {
                        return $record->roles->pluck('name')->toArray();
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
                //                Tables\Columns\TextColumn::make('deleted_at')->dateTime()->sortable()->visible(fn ($record) => $record->trashed()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\RestoreAction::make()->visible(fn($record) => $record->trashed()),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make()->visible(fn($record) => $record->trashed()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);

    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ChildrenRelationManager::class,
            RelationManagers\NotificationSettingsRelationManager::class,
            RelationManagers\RoleFolderPermissionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFolders::route('/'),
            'tree' => new PageRegistration(
                Pages\ListFoldersTree::class,
                fn(): RouteAlias => Route::get('/tree', Pages\ListFoldersTree::class)),
            'create' => Pages\CreateFolder::route('/create'),
            'edit' => Pages\EditFolder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['children', 'roles', 'ancestors.roles']);

        // URLクエリから 'tenant' パラメータを取得
        if ($tenantId = request()->query('tenant')) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('viewAny', Folder::class);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', Folder::class);
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
        return auth()->user()->can('delete', Folder::class);
    }
}
