<?php

namespace App\Filament\Resources\TagResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LedgerDefinesRelationManager extends RelationManager
{
    protected static string $relationship = 'LedgerDefine';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('LedgerDefine')
            ->columns([
                Tables\Columns\TextColumn::make('title'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //                \Filament\Actions\CreateAction::make(),
                //                \Filament\Actions\AttachAction::make(),
                //                \Filament\Actions\DetachAction::make(),
            ])
            ->actions([
                //                \Filament\Actions\EditAction::make(),
                //                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                /*                \Filament\Actions\BulkActionGroup::make([
                                    \Filament\Actions\DeleteBulkAction::make(),
                                ]),*/
            ]);
    }
}
