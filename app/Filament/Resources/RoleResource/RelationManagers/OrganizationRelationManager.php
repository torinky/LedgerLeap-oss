<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrganizationRelationManager extends RelationManager
{
    protected static string $relationship = 'Organizations';


    public static function getRecordTitleAttribute(): ?string
    {
        return "name";
    }

    /*
     * Support changing tab title in RelationManager.
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string)str(static::getRelationshipName())
            ->kebab()
            ->replace('-', ' ')
            ->headline();
    }

    protected static function getModelLabel(): string
    {
        return __('organiztions');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('organiztions');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('organiztion')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Support changing table heading by translations.
            ->heading(__('organiztions'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('name'))
                    ->searchable(),
            ])
            ->filters([

            ])->headerActions([
                AttachAction::make(),
            ])->actions([
                DetachAction::make(),
            ])->bulkActions([
                //
            ]);
    }
}
