<?php

namespace App\Filament\Resources\FolderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;



class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('ledger.folder.containing');
    }
    public static function getModelLabel(): string
    {
        return __('ledger.folders');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.folders');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('creator_id')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('modifier_id')
                    ->relationship('modifier', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('creator.name')->label('Creator'),
                Tables\Columns\TextColumn::make('modifier.name')->label('Modifier'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->bulleted(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
