<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Enums\FolderPermissionType;
use App\Models\RoleFolderPermission;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FolderRelationManager extends RelationManager
{
    protected static string $relationship = 'readableFolders';

    protected FolderPermissionType $permission = FolderPermissionType::READ;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
    }

    /*
     * Support changing tab title in RelationManager.
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string)str(static::getRelationshipName())
            ->kebab()
            ->replace('-', ' ')
            ->headline();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true; // 常にtrueを返すことで、権限チェックを無効化
    }

    protected static function getModelLabel(): string
    {
        return __((string)str(static::getRelationshipName()));
    }

    protected static function getPluralModelLabel(): string
    {
        return __((string)str(static::getRelationshipName()));
    }

    public function table(Table $table): Table
    {
        $role = $this->getOwnerRecord();
        $existingFolderIds = RoleFolderPermission::where('role_id', $role->id)
            ->pluck('folder_id');

        return $table
            // Support changing table heading by translations.
            ->heading(__((string)str(static::getRelationshipName())))
            ->columns([
                TextColumn::make('title')
                    ->label(__('title'))
                    ->searchable(),
            ])
            ->filters([

            ])->headerActions([
                AttachAction::make()->form([
                    SelectTree::make('recordId')
                        ->searchable()
                        ->label(__('ledger.folder.containing'))
                        ->relationship('parent', 'title', 'parent_id',
                            modifyQueryUsing: function ($query) use ($existingFolderIds) {
                                return $query->whereNotIn('folders.id', $existingFolderIds);
                            },
                            modifyChildQueryUsing: function ($query) use ($existingFolderIds) {
                                return $query->whereNotIn('folders.id', $existingFolderIds);
                            }
                        )
                        ->withCount()
                        ->enableBranchNode()
//                        ->alwaysOpen()
                        ->defaultOpenLevel(10),
                ])
                    ->translateLabel()
                    ->using(function (array $data, string $model): ?Model {
                        if (is_null($data['recordId'])) {
                            return null;
                        }
                        $role = $this->getOwnerRecord();
                        $data['role_id'] = $role->id;
                        $data['folder_id'] = $data['recordId'];
                        $data['permission'] = $this->permission;
                        $data['modifier_id'] = auth()->id();
                        unset($data['recordId']);

                        return RoleFolderPermission::updateOrCreate(['role_id' => $data['role_id'], 'folder_id' => $data['folder_id']], $data);
                    }),
            ])->actions([
                DetachAction::make(),
            ])->bulkActions([
                //
            ]);
    }

    public function form(Form $form): Form
    {
        $modifierId = auth()->id();

        return $form
            ->schema([
                TextInput::make('title')
                    ->label(__((string)str(static::getRelationshipName()))),

                Hidden::make('modifier_id')
                    ->default($modifierId),
            ]);
    }
}
