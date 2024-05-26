<?php

namespace App\Filament\Resources\Synonym;

use App\Models\Synonym\LedgerWordSynonym;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LedgerWordSynonymResource extends Resource
{
    protected static ?string $model = LedgerWordSynonym::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //                ID::make('id')->sortable(),

                Select::make('ledger_word_id')
                    ->label('Keyword')
                    ->relationship(name: 'word', titleAttribute: 'name')
                    ->searchable(),

                Select::make('synonym_id')
                    ->label('Synonym')
//                    ->multiple()
                    ->relationship(name: 'synonym', titleAttribute: 'name')
                    ->searchable(),

                /*                Select::make('user')
                    ->relationship(name: 'user_id', titleAttribute: 'name')*/
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),

                Tables\Columns\TextColumn::make('ledger_word_id')->label('Keyword')->searchable()
                    ->description(fn(LedgerWordSynonym $record): string => $record->word->name),

                Tables\Columns\TextColumn::make('synonym_id')->label('Synonym')->searchable()
                    ->description(fn(LedgerWordSynonym $record): string => $record->synonym->name),

                Tables\Columns\TextColumn::make('user_id')->label('User')->searchable(),
                //
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => LedgerWordSynonymResource\Pages\ListLedgerWordSynonyms::route('/'),
            'create' => LedgerWordSynonymResource\Pages\CreateLedgerWordSynonym::route('/create'),
            'edit' => LedgerWordSynonymResource\Pages\EditLedgerWordSynonym::route('/{record}/edit'),
        ];
    }
}
