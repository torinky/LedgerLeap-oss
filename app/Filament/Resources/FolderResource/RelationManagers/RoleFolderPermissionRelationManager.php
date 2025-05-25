<?php

namespace App\Filament\Resources\FolderResource\RelationManagers;

use App\Enums\FolderPermissionType;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RoleFolderPermissionRelationManager extends RelationManager
{
    protected static string $relationship = 'accessibleRoles'; // Folderモデルに定義されているリレーションシップ名

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ledger.folder.permission');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.folder.permission');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ledger.folder.permission');
    }

    public function table(Table $table): Table
    {
        $folder = $this->getOwnerRecord();
        $existingRoleIds = RoleFolderPermission::where('folder_id', $folder->id)
//            ->where('permission', $this->permission->value)
            ->WhereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
            ->pluck('role_id');

        return $table
            ->heading(__('ledger.folder.permission'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('ledger.role'))
                    ->searchable(),
                SelectColumn::make('permission')
                    ->label(__('ledger.folder.permission'))
                    ->options(function () {
                        return collect(FolderPermissionType::cases())
                            ->filter(function ($folderPermission) {
                                return $folderPermission->isAccessType();
                            })
                            ->mapWithKeys(function ($folderPermission) {
                                return [$folderPermission->value => __('permission.name.' . $folderPermission->value)];
                            })->toArray();
                    })
                    ->afterStateUpdated(function ($state, $column, $record) {
                        $folder = $this->getOwnerRecord();
                        $roleId = $record->id;
                        $folderId = $folder->id;
                        $roleFolderPermission = RoleFolderPermission::where('role_id', $roleId)->where('folder_id', $folderId)->first();

                        if (!is_null($roleFolderPermission)) {
                            $roleFolderPermission->permission = $state;
                            $roleFolderPermission->save();
                        }
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()->form([
                    Select::make('recordId')
                        ->label(__('ledger.role'))
                        ->options(Role::query()->whereNotIn('id', $existingRoleIds)->pluck('name', 'id'))->searchable()
                        ->required(),
                    Select::make('permission')
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
                        ->required(),
                ])
                    ->using(function (array $data, string $model) {
                        //                                                dd($data,$model);
                        if (is_null($data['recordId'])) {
                            return null;
                        }
                        $folder = $this->getOwnerRecord();
                        $data['role_id'] = $data['recordId'];
                        $data['folder_id'] = $folder->id;
                        $data['notification_type_id'] = null;
                        $data['modifier_id'] = auth()->id();

                        $existFolderPermission = RoleFolderPermission::where('role_id', $data['role_id'])
                            ->whereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
                            ->where('folder_id', $data['folder_id'])
                            ->first();
                        if ($existFolderPermission) {
                            $existFolderPermission->update($data);

                            return $existFolderPermission;
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
