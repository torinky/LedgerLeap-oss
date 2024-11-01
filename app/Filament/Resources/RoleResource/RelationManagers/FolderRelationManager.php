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

class FolderRelationManager extends RelationManager
{
    protected static string $relationship = 'folders';

    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
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

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true; // 常にtrueを返すことで、権限チェックを無効化
    }

    protected static function getModelLabel(): string
    {
        return __((string)str(static::getRelationshipName()));
    }

    protected static function getPluralModelLabel(): string
    {
        return __((string)str(static::getRelationshipName()));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label(__((string)str(static::getRelationshipName()))),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Support changing table heading by translations.
            ->heading(__((string)str(static::getRelationshipName())))
            ->columns([
                TextColumn::make('title')
                    ->label(__('title'))
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
