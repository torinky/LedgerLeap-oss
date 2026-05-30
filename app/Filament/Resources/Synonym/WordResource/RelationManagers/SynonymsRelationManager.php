<?php

namespace App\Filament\Resources\Synonym\WordResource\RelationManagers;

use App\Filament\Resources\Synonym\SynonymResource;
use App\Models\Synonym\Word;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SynonymsRelationManager extends RelationManager
{
    public static $primaryKey = 'wordid';

    public static string $relationship = 'synonyms';

    public static $resource = SynonymResource::class;

    public static function getRelatedRecords(): Collection
    {
        $word = Word::find(request()->get('record'));

        return $word->synonyms;
    }

    /*    public function getRelatedRecords(): \Illuminate\Database\Eloquent\Collection
        {
            return $this->ownerRecord->synonyms;
        }*/

    public function getHeading(): string
    {
        return "Synonyms of {$this->ownerRecord->word}";
    }

    //    protected static string $relationship = 'synonyms';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('synonym')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('synonym')
            ->columns([
                Tables\Columns\TextColumn::make('synonym'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
