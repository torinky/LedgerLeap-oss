<?php

namespace App\Filament\Resources\FolderResource\RelationManagers;

use App\Filament\Resources\FolderResource;
use App\Models\Folder;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('ledger.folder.containing');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.folders');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.folders');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label(__('ledger.folder.title'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('ledger.folder.title')),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('ledger.creator.name')),
                Tables\Columns\TextColumn::make('modifier.name')
                    ->label(__('ledger.modifier.name')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ledger.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\Action::make('attach_children')
                    ->label(__('ledger.attach_existing_folder'))
                    ->icon('heroicon-o-paper-clip')
                    ->form([
                        SelectTree::make('children_ids')
                            ->label(__('ledger.folders_to_attach_under'))
                            ->multiple()
                            ->relationship('parent', 'title', 'parent_id')
                            ->enableBranchNode()
                            ->searchable()
                            ->clearable()
                            ->placeholder(__('ledger.select_folders_to_attach'))
                            ->hiddenOptions(function (RelationManager $livewire): array {
                                $ownerRecord = $livewire->getOwnerRecord();
                                $ancestorAndSelfIds = $ownerRecord->ancestorsAndSelf($ownerRecord->id)->pluck('id')->toArray();
                                $childrenIds = $ownerRecord->children()->pluck('id')->toArray();

                                return array_merge($ancestorAndSelfIds, $childrenIds);
                            })
                            ->required(),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $ownerRecord = $livewire->getOwnerRecord();
                        Folder::whereIn('id', $data['children_ids'])
                            ->update(['parent_id' => $ownerRecord->id]);
                    })
                    ->modalWidth('3xl'),
                \Filament\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->url(fn (Folder $record): string => FolderResource::getUrl('edit', ['record' => $record])),
                \Filament\Actions\Action::make('detach_child')
                    ->label(__('ledger.detach'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('ledger.detach_confirmation_title'))
                    ->modalDescription(__('ledger.detach_confirmation_description'))
                    ->action(fn (Folder $record) => $record->update(['parent_id' => null])),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
