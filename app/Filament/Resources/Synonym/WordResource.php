<?php

namespace App\Filament\Resources\Synonym;

use App\Filament\Resources\Synonym\WordResource\Pages;
use App\Filament\Resources\Synonym\WordResource\RelationManagers;
use App\Models\Synonym\Word;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WordResource extends Resource
{
    protected static ?string $model = Word::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Textarea::make('wordid')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('lang')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('lemma')
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
                Tables\Columns\TextColumn::make('lemma')->searchable(),
                Tables\Columns\TextColumn::make('pron'),
                Tables\Columns\TextColumn::make('pos'),
                //
            ])
            ->filters([
                Filter::make('jpn')
                    ->query(fn (Builder $query): Builder => $query->where('lang', 'jpn')),
                Filter::make('eng')
                    ->query(fn (Builder $query): Builder => $query->where('lang', 'eng')),
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

    public static function getNavigationIcon(): ?string
    {
        return null;
    }
}
