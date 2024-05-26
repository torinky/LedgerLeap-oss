<?php

namespace App\Filament\Resources\Synonym;

use App\Models\Synonym\LedgerWord;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LedgerWordResource extends Resource
{
    protected static ?string $model = LedgerWord::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('name')->rules('required'),
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name'),                //
            ])
            ->searchable() // 検索機能を有効化する場合（オプション）
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
            'index' => LedgerWordResource\Pages\ListLedgerWords::route('/'),
            'create' => LedgerWordResource\Pages\CreateLedgerWord::route('/create'),
            'edit' => LedgerWordResource\Pages\EditLedgerWord::route('/{record}/edit'),
        ];
    }
}
