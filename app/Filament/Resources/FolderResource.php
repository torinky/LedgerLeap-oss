<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FolderResource\Pages;
use App\Filament\Resources\FolderResource\RelationManagers;
use App\Models\Folder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use App\Services\ConfidentialityLevelService;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Panel;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Filament resource for managing folder hierarchy and permissions.
 *
 * Provides CRUD for the tenant-scoped folder tree, folder-level permission
 * assignment, confidentiality level configuration, and parent-child
 * folder relationship management.
 */
class FolderResource extends Resource
{
    public static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Folder::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    public static function getLabel(): string
    {
        return __('ledger.folders');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.folder.title');
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('ledger.basic_information'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('ledger.folder.title'))
                            ->required()
                            ->maxLength(255),
                        Select::make('tenant_id')
                            ->label(__('ledger.tenant'))
                            ->options(static::tenantOptions())
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('parent_id', null); // Clear parent folder selection when tenant changes
                            })
                            ->required() // tenant_idを必須にする
                            ->disabledOn('edit') // 編集時は無効化
                            ->default(fn () => static::resolveTenantId()),
                        Forms\Components\Hidden::make('creator_id')
                            ->default(fn () => auth()->id())
                            ->disabledOn('edit'), // 作成時のみ記録
                        Forms\Components\Hidden::make('modifier_id')
                            ->default(fn () => auth()->id()), // 常に現在のユーザーを設定
                        SelectTree::make('parent_id')
                            ->label(__('ledger.folder.parent'))
                            ->relationship(
                                'parent', // name
                                'title', // titleAttribute
                                'parent_id', // parentAttribute
                                modifyQueryUsing: fn (EloquentBuilder $query, callable $get) => Tenancy::central(function () use ($query, $get) {
                                    $selectedTenantId = $get('tenant_id');
                                    if ($selectedTenantId) {
                                        $query->where('tenant_id', $selectedTenantId);
                                    }

                                    return $query->orderBy('_lft');
                                })
                            )
                            ->enableBranchNode()
                            ->defaultOpenLevel(1)
                            ->searchable(), // 親フォルダの検索を可能にする
                    ])->columns(2),

                Section::make(__('ledger.confidentiality.level.label'))
                    ->schema(static::confidentialityFormFields())
                    ->columns(2),

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
                                    $syncData = collect($state)->mapWithKeys(fn ($roleId) => [$roleId => ['type' => 'inspector']])->all();
                                    // attachではなくsyncを使うのが一般的。既存の関連は解除され、新しいものだけが残る。
                                    // もし既存の関連を維持しつつ追加したい場合は、ロジックを調整する必要がある。
                                    $record->requiredInspectorRoles()->sync($syncData);
                                } else {
                                    // 何も選択されなかった場合は空でsync（全ての関連を解除）
                                    $record->requiredInspectorRoles()->sync([]);
                                }
                            }),
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
                                    $syncData = collect($state)->mapWithKeys(fn ($roleId) => [$roleId => ['type' => 'approver']])->all();
                                    $record->requiredApproverRoles()->sync($syncData);
                                } else {
                                    $record->requiredApproverRoles()->sync([]);
                                }
                            }),
                    ])->columns(2),

                // Forms\Components\Hidden::make('lft'), // NodeTrait が自動で処理するはず
                // Forms\Components\Hidden::make('rgt'),
                // Forms\Components\Hidden::make('lvl'),
            ]);
    }

    public static function confidentialityFormFields(): array
    {
        return [
            Select::make('confidentiality_level')
                ->label(__('ledger.confidentiality.level.label'))
                ->default('public')
                ->required()
                ->options(static::confidentialityLevelOptions()),
            Select::make('confidentiality_scopes')
                ->label(__('ledger.confidentiality.scope.label'))
                ->multiple()
                ->searchable()
                ->preload()
                ->default([])
                ->options(static::confidentialityScopeOptions())
                ->afterStateHydrated(function (Select $component, $state): void {
                    $component->state(ConfidentialityLevelService::buildScopeChoices($state));
                })
                ->dehydrateStateUsing(function ($state): array {
                    return ConfidentialityLevelService::parseScopeChoices($state ?? []);
                }),
        ];
    }

    protected static function confidentialityLevelOptions(): array
    {
        return collect(ConfidentialityLevelService::selectOptions())
            ->mapWithKeys(fn (array $option) => [$option['id'] => $option['name']])
            ->all();
    }

    protected static function confidentialityScopeOptions(): array
    {
        return collect(ConfidentialityLevelService::allScopes())
            ->mapWithKeys(fn (array $option) => [$option['id'] => $option['name']])
            ->all();
    }

    public static function tenantOptions(): array
    {
        return Tenancy::central(function () {
            return Tenant::all()->mapWithKeys(function ($tenant) {
                return [$tenant->id => $tenant->name ?: $tenant->id];
            })->all();
        });
    }

    public static function resolveTenantId(): ?string
    {
        $tenantId = request()->query('tenant');

        if (filled($tenantId)) {
            return (string) $tenantId;
        }

        $tenantId = session('filament_from_tenant_id');

        if (filled($tenantId)) {
            return (string) $tenantId;
        }

        return tenant()?->id
            ? (string) tenant()->id
            : null;
    }

    public static function tenantContextParameters(): array
    {
        return filled($tenantId = static::resolveTenantId())
            ? ['tenant' => $tenantId]
            : [];
    }

    public static function tenantScopedQuery(): QueryBuilder
    {
        /** @var QueryBuilder $query */
        $query = Folder::query()->with('roles');

        if ($tenantId = static::resolveTenantId()) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    public static function tenantSwitchAction(string $page): Action
    {
        return Action::make('switchTenant')
            ->label(__('ledger.tenant'))
            ->icon('heroicon-o-building-office-2')
            ->form([
                Select::make('tenant_id')
                    ->label(__('ledger.tenant'))
                    ->options(static::tenantOptions())
                    ->default(static::resolveTenantId())
                    ->required()
                    ->searchable()
                    ->preload(),
            ])
            ->action(function (array $data) use ($page) {
                return redirect()->to(static::getUrl($page, ['tenant' => $data['tenant_id']]));
            });
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
                EditAction::make(),
                RestoreAction::make()->visible(fn ($record) => $record->trashed()),
                DeleteAction::make(),
                ForceDeleteAction::make()->visible(fn ($record) => $record->trashed()),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
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
            'index' => new PageRegistration(
                Pages\ListFolders::class,
                fn (Panel $panel): \Illuminate\Routing\Route => Route::get('/', Pages\ListFolders::class)
                    ->middleware(Pages\ListFolders::getRouteMiddleware($panel))
                    ->withoutMiddleware(Pages\ListFolders::getWithoutRouteMiddleware($panel))
            ),
            'tree' => new PageRegistration(
                Pages\ListFoldersTree::class,
                fn (Panel $panel): \Illuminate\Routing\Route => Route::get('/tree', Pages\ListFoldersTree::class)
                    ->middleware(Pages\ListFoldersTree::getRouteMiddleware($panel))
                    ->withoutMiddleware(Pages\ListFoldersTree::getWithoutRouteMiddleware($panel))
            ),
            'create' => new PageRegistration(
                Pages\CreateFolder::class,
                fn (Panel $panel): \Illuminate\Routing\Route => Route::get('/create', Pages\CreateFolder::class)
                    ->middleware(Pages\CreateFolder::getRouteMiddleware($panel))
                    ->withoutMiddleware(Pages\CreateFolder::getWithoutRouteMiddleware($panel))
            ),
            'edit' => new PageRegistration(
                Pages\EditFolder::class,
                fn (Panel $panel): \Illuminate\Routing\Route => Route::get('/{record}/edit', Pages\EditFolder::class)
                    ->middleware(Pages\EditFolder::getRouteMiddleware($panel))
                    ->withoutMiddleware(Pages\EditFolder::getWithoutRouteMiddleware($panel))
            ),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = static::tenantScopedQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['children', 'roles', 'ancestors.roles']);

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
