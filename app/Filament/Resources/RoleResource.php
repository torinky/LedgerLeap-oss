<?php

namespace App\Filament\Resources;

use Althinect\FilamentSpatieRolesPermissions\Resources\RoleResource as BaseRoleResource;
use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers\TagsRelationManager;
use App\Models\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

            ]);
    }

    public static function table(Table $table): Table
    {
        $parentTable = parent::table($table);
        $columns = $parentTable->getColumns();

        return $table
            ->columns([
//                ...parent::table($table)->getColumns(),
                ...$columns,
                Tables\Columns\TextColumn::make('tags.name')
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
