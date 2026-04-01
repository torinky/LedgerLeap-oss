<?php

namespace App\Filament\Resources\Synonym;

use App\Filament\Resources\Synonym\TansiResource\Pages;
use App\Models\Synonym\Tansi;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TansiResource extends Resource
{
    protected static ?string $model = Tansi::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Textarea::make('pronunciation1')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('pronunciation2')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('category1')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('category2')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('CANDIDATES')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
                Tables\Columns\TextColumn::make('pronunciation1')->searchable(),
                Tables\Columns\TextColumn::make('pronunciation2')->searchable(),
                Tables\Columns\TextColumn::make('category1'),
                Tables\Columns\TextColumn::make('category2'),
                Tables\Columns\TextColumn::make('CANDIDATES')->searchable(),
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
            'index' => Pages\ManageTansis::route('/'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ledger.setting');
    }

    public static function getNavigationSort(): ?int
    {
        return 10; // 数字が小さいほど上に表示されます
    }

    public static function getNavigationIcon(): ?string
    {
        return null;
    }
}
