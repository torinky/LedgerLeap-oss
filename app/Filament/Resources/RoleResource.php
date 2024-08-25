<?php

namespace App\Filament\Resources;

use Althinect\FilamentSpatieRolesPermissions\Resources\RoleResource as BaseRoleResource;
use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers\FolderRelationManager;
use App\Filament\Resources\RoleResource\RelationManagers\OrganizationRelationManager;
use App\Filament\Resources\RoleResource\RelationManagers\TagsRelationManager;
use App\Models\Folder;
use App\Models\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoleResource extends BaseRoleResource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $parentForm = parent::form($form);
        $components = $parentForm->getComponents();

        return $form
            ->schema([
                ...$components,

                Select::make('tags')
                    ->multiple()
                    ->relationship('tags', 'name')
                    ->preload()
                    ->searchable(),

                Select::make('scope folders')
                    ->options(function () {
                        return Folder::treeList(Folder::get()->toTree());
                    })
                    ->multiple()
                    ->afterStateHydrated(function (Select $component, $state, ?Model $record) {
                        if ($record) {
                            $folderIds = Folder::role($record)->pluck('id')->toArray();
                            $component->state($folderIds);
                        }
                    })
                    ->afterStateUpdated(function ($state, ?Model $record) {
                        if ($record) {
                            $allFolders = Folder::get();

                            foreach ($allFolders as $allFolder) {
                                if (in_array($allFolder->id, $state)) {
                                    $allFolder->assignRole($record);
                                } else {
                                    $allFolder->removeRole($record);
                                }
                            }
                        }
                    })
                    ->dehydrated(false),
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
                Tables\Columns\TextColumn::make('tags.name')
                    ->badge(),
                Tables\Columns\TextColumn::make('folder.title')
                    ->label('Scope Folders')
                    ->getStateUsing(function ($record) use ($allFolderList) {
                        $selectedFolderIds = Folder::role($record)->pluck('id')->toArray();
                        $folders = [];
                        foreach ($allFolderList as $folderId => $folderTitle) {
                            if (in_array($folderId, $selectedFolderIds)) {
                                $folders[] = $folderTitle;
                            }
                        }

                        return $folders;
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('organizations.name')
                    ->badge(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->badge(),
            ])
            ->filters($parentTable->getFilters())
            ->actions($parentTable->getActions())
            ->bulkActions($parentTable->getBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            ...parent::getRelations(),
            TagsRelationManager::class,
            FolderRelationManager::class,
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
