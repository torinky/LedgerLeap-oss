<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Filament\Resources\OrganizationResource\RelationManagers;
use App\Models\Organization;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Route;

class OrganizationResource extends Resource
{
    public static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    // グローバル検索の結果に表示するタイトルとして'name'カラムを使用
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'org_id']; // 'name'と'org_id'を検索対象に
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'ID' => $record->org_id,
        ];
    }

/*    public static function form(Form $form): Form
    {
        return __('ledger.organization');
    }*/
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('org_id')
                    ->label('Organization ID')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label(__('ledger.organizations.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label(__('ledger.description'))
                    ->maxLength(65535),
                Forms\Components\Select::make('roles')
                    ->label(__('ledger.settings.roles'))
                    ->multiple()
                    ->relationship('roles', 'name'),
                /*                Forms\Components\Select::make('permissions')
                                    ->multiple()
                                    ->relationship('permissions', 'name'),*/
                SelectTree::make('parent_id')
                    ->label(__('ledger.organizations.parent'))
                    ->relationship('parent', 'name', 'parent_id') // まずはリレーションで全組織を取得
                    ->searchable()
                    ->clearable()
                    ->placeholder(__('ledger.folder.form.option.no_parent'))
                    ->defaultOpenLevel(5)
                    // 編集時に自分自身とその子孫を選択できないようにする
                    ->hiddenOptions(function (?Model $record): array {
                        if ($record === null) {
                            return []; // 新規作成時は何も非表示にしない
                        }
                        // 自分自身と、その配下にあるすべての子孫組織のIDを返す
                        return $record->descendantsAndSelf($record->id)->pluck('id')->toArray();
                    }),
            ]);
    }



    public static function getRelations(): array
    {
        return [
            RelationManagers\UserRelationManager::class,
            RelationManagers\ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
//            'index' => Pages\ListOrganizations::route('/'),
            // `::route('/')` の代わりに `PageRegistration` を直接使用します
            'index' => new PageRegistration(
                Pages\ListOrganizations::class,
                // 第2引数をクロージャに変更し、Routeを定義します
                fn (): \Illuminate\Routing\Route => Route::get('/', Pages\ListOrganizations::class)
                    // `static::getPanel()` を `Filament::getPanel()` に修正
                    ->middleware(Pages\ListOrganizations::getRouteMiddleware(Filament::getPanel()))
                    ->withoutMiddleware(Pages\ListOrganizations::getWithoutRouteMiddleware(Filament::getPanel()))
            ),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['children', 'roles', 'ancestors.roles']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('viewAny', Organization::class);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', Organization::class);
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
        return auth()->user()->can('delete', Organization::class);
    }
}
