<?php

namespace App\Filament\Resources\Synonym;

use App\Filament\Resources\Synonym\WordResource\Pages;
use App\Filament\Resources\Synonym\WordResource\RelationManagers;
use App\Models\Synonym\Word;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WordResource extends Resource
{
    protected static ?string $model = Word::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('wordid')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('lang')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('lema')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('pron')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('pos')
                    ->columnSpanFull(),
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wordid'),
                Tables\Columns\TextColumn::make('lang'),
                Tables\Columns\TextColumn::make('pron'),
                Tables\Columns\TextColumn::make('pos'),
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SynonymsRelationManager::class,
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWords::route('/'),
            'create' => Pages\CreateWord::route('/create'),
            'edit' => Pages\EditWord::route('/{record}/edit'),
        ];
    }
}
