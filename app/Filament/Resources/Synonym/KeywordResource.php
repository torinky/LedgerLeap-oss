<?php

namespace App\Filament\Resources\Synonym;

use App\Filament\Resources\Synonym\KeywordResource\Pages;
use App\Filament\Resources\Synonym\KeywordResource\RelationManagers;
use App\Models\Synonym\Keyword;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KeywordResource extends Resource
{
    protected static ?string $model = Keyword::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('pos')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('name')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('src')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
                Tables\Columns\TextColumn::make('pos'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('src'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageKeywords::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SynonymsRelationManager::class,
        ];
    }
}
