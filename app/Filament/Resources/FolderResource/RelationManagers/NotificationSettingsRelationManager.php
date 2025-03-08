<?php

namespace App\Filament\Resources\FolderResource\RelationManagers;

use App\Enums\FolderPermissionType;
use App\Filament\Tables\Actions\DeleteNotification;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NotificationSettingsRelationManager extends RelationManager
{
    protected static string $relationship = 'notificationSettings';

    protected static ?string $recordTitleAttribute = 'name'; // ロールの表示名

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ledger.folder.notification'); // タブのタイトル
    }

    public static function getModelLabel(): string
    {
        return __('ledger.folder.notification'); // タブのタイトル
    }

    public static function getPluralModelLabel(): string
    {
        return __('ledger.folder.notification');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('ledger.folder.notification')) // テーブルの見出し
            ->columns([
                TextColumn::make('name')
                    ->label(__('ledger.role_name')),
                TextColumn::make('notificationType.name') // 追加: 通知タイプ名
                ->label(__('ledger.notification_type'))
                    ->formatStateUsing(function ($record) {
                        //                dd($record);
                        return $record->notificationType ? __('ledger.notification_types.' . $record->notificationType->name) : null;
                    }),
                IconColumn::make('permission') // 追加: 通知の ON/OFF
                ->label(__('ledger.notify'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger')
//                    ->getStateUsing(fn ( $record): bool => $record->permission === FolderPermissionType::NOTIFY_ON),
                    ->getStateUsing(function (Role $record): bool {
                        return $record->permission === FolderPermissionType::NOTIFY_ON->value;
                    }),
            ])
            ->modifyQueryUsing(function ($query) {
                $query->whereIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF]);
            })
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->form([
                        Select::make('role_id') // 変更
                        ->label(__('ledger.role'))
                            ->options(Role::pluck('name', 'id')->toArray()) // Role モデルから選択肢を取得
                            ->required()
                            ->searchable(),
                        CheckboxList::make('notification_type_ids')
                            ->label(__('ledger.notification_type'))
                            ->options(function () {
                                return NotificationType::all()->mapWithKeys(function ($notificationType) {
                                    return [$notificationType->id => __('ledger.notification_types.' . $notificationType->name)];
                                })->toArray();
                            })
                            ->columns(3)
                            ->default(function () {
                                return NotificationType::where('default_notify', '=', true)->pluck('id')->toArray();
                            })
                            ->required(),
                        Hidden::make('recordId')
                            ->default('null'),
                    ])
//                    ->before(function (AttachAction $action, $livewire) {
                    ->using(function ($record, array $data) {

//                            $data = $livewire->mountedTableActionsData[0];
//                        dd($record, $data);
                        if (!isset($data['notification_type_ids'])) {
                            return null;
                        }
                        if (is_null($data['recordId'])) {
                            return null;
                        }

                        $role = Role::find($data['role_id']);
//                        $role = $record;
                        $folder = $this->getOwnerRecord();
                        $modifierId = auth()->id();
                        $selectedNotificationTypeIds = $data['notification_type_ids'];

                        // 既存の通知設定をすべて NOTIFY_OFF に更新
                        RoleFolderPermission::where('role_id', $role->id)
                            ->where('folder_id', $folder->id)
                            ->where('permission', FolderPermissionType::NOTIFY_ON)
                            ->update(['permission' => FolderPermissionType::NOTIFY_OFF]);

//                        dd($data,$existingSettings);

                        // 選択された通知タイプについて、role_folder_permissions レコードを作成/更新
                        foreach ($selectedNotificationTypeIds as $notificationTypeId) {
                            RoleFolderPermission::updateOrCreate(
                                [
                                    'role_id' => $role->id,
                                    'folder_id' => $folder->id,
                                    'permission' => FolderPermissionType::NOTIFY_OFF,
                                    'notification_type_id' => $notificationTypeId,
                                ],
                                [
                                    'role_id' => $role->id,
                                    'folder_id' => $folder->id,
                                    'modifier_id' => $modifierId,
                                    'permission' => FolderPermissionType::NOTIFY_ON, // 通知を ON に設定
                                    'notification_type_id' => $notificationTypeId,
                                ]
                            );
                        }

                    })

            ])
            ->actions([
                EditAction::make()
                    ->form([
                        CheckboxList::make('notification_types')
                            ->label(__('ledger.notification_type'))
                            ->options(function () {
                                return NotificationType::all()->mapWithKeys(function ($notificationType) {
                                    return [$notificationType->id => __('ledger.notification_types.' . $notificationType->name)];
                                })->toArray();
                            })
                            ->columns(3)
                            ->afterStateHydrated(function (CheckboxList $component, $record) {
//                                                            dd($record);
                                $selected = RoleFolderPermission::where('role_id', $record->id)
                                    ->where('folder_id', $record->folder_id)
                                    ->where('permission', FolderPermissionType::NOTIFY_ON->value)
                                    ->pluck('notification_type_id')->toArray();
                                // dd($record, $selected);
                                $component->state($selected);
                            })
                    ])
                    ->using(function (Model $record, array $data): Model {
//                                        dd($data,$record);
                        // 通知設定を更新

                        $folder = $this->getOwnerRecord();
                        $folderId = $folder->id;

                        $notificationTypeIds = $data['notification_types'];
                        $modifierId = auth()->id();
                        $roleId = $record->role_id;
                        $existingSettings = RoleFolderPermission::where('role_id', $roleId)
                            ->where('folder_id', $folderId)
                            ->whereIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
                            ->get();

                        foreach ($existingSettings as $setting) {
                            //                            dd($setting,$setting->notification_type_id,$notificationTypeIds);
                            if (in_array($setting->notification_type_id, $notificationTypeIds)) {
                                $setting->update([
                                    'modifier_id' => $modifierId,
                                    'permission' => FolderPermissionType::NOTIFY_ON->value,
                                ]);
                            } else {
                                $setting->update([
                                    'modifier_id' => $modifierId,
                                    'permission' => FolderPermissionType::NOTIFY_OFF->value,
                                ]);

                            }
                        }

                        return $record;
                    }),
                DeleteNotification::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Role モデルの編集はここでは行わない
            ]);
    }
}
