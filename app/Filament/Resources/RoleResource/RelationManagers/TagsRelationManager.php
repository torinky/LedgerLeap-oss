<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    public function form(Schema $schema): Schema
    {
        /*        return $schema
                    ->schema([
                        Forms\Components\TextInput::make('tag')
                            ->required()
                            ->maxLength(255),
                    ]);*/
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        /*        return $table
                    ->recordTitleAttribute('tag')
                    ->columns([
                        Tables\Columns\TextColumn::make('tag'),
                    ])
                    ->filters([
                        //
                    ])
                    ->headerActions([
                        \Filament\Actions\CreateAction::make(),
                    ])
                    ->actions([
                        \Filament\Actions\EditAction::make(),
                        \Filament\Actions\DeleteAction::make(),
                    ])
                    ->bulkActions([
                        \Filament\Actions\BulkActionGroup::make([
                            \Filament\Actions\DeleteBulkAction::make(),
                        ]),
                    ]);*/
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
                \Filament\Actions\AttachAction::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DetachAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DetachBulkAction::make(),
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true; // 常にtrueを返すことで、権限チェックを無効化
    }
}
