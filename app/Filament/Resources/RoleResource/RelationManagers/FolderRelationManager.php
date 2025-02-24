<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Enums\FolderPermissionType;
use App\Models\RoleFolderPermission;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
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
    protected static string $relationship = 'accessibleFolders';


    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
    }

    /*
     * Support changing tab title in RelationManager.
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ledger.folder.permission');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true; // 常にtrueを返すことで、権限チェックを無効化
    }

    protected static function getModelLabel(): string
    {
        return __('ledger.folder.permission');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('ledger.folder.permission');
    }

    public function table(Table $table): Table
    {
        $role = $this->getOwnerRecord();
        $existingFolderIds = RoleFolderPermission::where('role_id', $role->id)
//            ->where('permission', $this->permission->value)
            ->WhereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
            ->pluck('folder_id');

        return $table
            // Support changing table heading by translations.
            ->heading(__('ledger.folder.permission'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('ledger.folder.title'))
                    ->searchable(),
                TextColumn::make('permission.name')
                    ->label(__('ledger.permission'))
                    ->formatStateUsing(function ($record) {
                        //                dd($record);
                        return $record->permission ? __('ledger.permissions.' . $record->permission->value) : null;
                    }),
            ])
            ->modifyQueryUsing(function ($query) {
                $query->whereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF]);
            })
            ->filters([

            ])->headerActions([
                AttachAction::make()->form([
                    SelectTree::make('recordId')
                        ->searchable()
                        ->label(__('ledger.folder.containing'))
                        ->relationship('parent', 'title', 'parent_id'
                            ,
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
                    Select::make('permission') // 変更: 複数選択可能な CheckboxList
                    ->label(__('ledger.permission'))
                        ->options(function () {
                            return collect(FolderPermissionType::cases())
                                ->filter(function ($folderPermission) {
                                    return $folderPermission->isAccessType();
                                })
                                ->mapWithKeys(function ($folderPermission) {
                                    return [$folderPermission->value => __('ledger.permissions.' . $folderPermission->value)];
                                })->toArray();
                        })
                        ->columns(3)
                        /*                        ->default(function () {
                                                    return NotificationType::where('default_notify', '=', true)->pluck('id')->toArray();
                                                })*/
                        ->required(),
                ])
//                    ->translateLabel()
                    ->using(function (array $data, string $model) {
//                        dd($data,$model);
                        if (is_null($data['recordId'])) {
                            return null;
                        }
                        $role = $this->getOwnerRecord();
                        $data['role_id'] = $role->id;
                        $data['folder_id'] = $data['recordId'];
                        $data['notification_type_id'] = null;
                        $data['modifier_id'] = auth()->id();
                        unset($data['recordId']);

                        $existFolderPermisson = RoleFolderPermission::where('role_id', $role->id)
                            ->whereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
                            ->where('folder_id', $data['folder_id'])
                            ->first();
                        if ($existFolderPermisson) {
                            $existFolderPermisson->update($data);
                            return $existFolderPermisson;
                        } else {
                            return RoleFolderPermission::create($data);
                        }
                        return null;

                    }),
            ])->actions([
                DetachAction::make(),
            ])->bulkActions([
                //
            ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);

    }
}
