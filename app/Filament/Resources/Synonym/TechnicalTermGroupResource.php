<?php

namespace App\Filament\Resources\Synonym;

use App\Filament\Resources\Synonym\TechnicalTermGroupResource\Pages;
use App\Models\Synonym\TechnicalTermGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TechnicalTermGroupResource extends Resource
{
    protected static ?string $model = TechnicalTermGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Repeater::make('synonyms')
                            ->label('Synonyms')
                            ->required()
                            ->schema([
                                TextInput::make('synonym')
                                    ->label('Synonym')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Add Synonym'),

                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('synonyms')->limit(500)->searchable(),
                Tables\Columns\TextColumn::make('modifier.name')->label('Modifier'),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated At'),
                Tables\Columns\TextColumn::make('creator.name')->label('Creator'),
                Tables\Columns\TextColumn::make('created_at')->label('Created At'),
            ])
            ->searchable() // 検索機能を有効化する場合（オプション）
            ->filters([])
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
            'index' => Pages\ListTechnicalTermGroups::route('/'),
            'create' => Pages\CreateTechnicalTermGroup::route('/create'),
            'edit' => Pages\EditTechnicalTermGroup::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('ledger.technical_term');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.technical_term'); // または適切な翻訳キー
    }

    public static function getPluralModelLabel(): string
    {
        return __('ledger.technical_term'); // 複数形の翻訳キー
    }
}
