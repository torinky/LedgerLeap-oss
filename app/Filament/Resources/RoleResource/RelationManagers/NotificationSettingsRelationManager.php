<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\NotificationType;
use App\Models\RoleFolderPermission;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;


class NotificationSettingsRelationManager extends RelationManager
{
    protected static string $relationship = 'notificationSettings'; // Role モデルのリレーションシップ名

    //    protected static string $relationship = 'roleFolderPermissions';

    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ledger.notification_settings'); // タブのタイトル
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true; // 権限チェックは一旦無効化
    }

    protected static function getModelLabel(): string
    {
        return __('ledger.notification_setting');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('ledger.notification_settings');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('ledger.notification_settings')) // テーブルの見出し
            ->columns([
                TextColumn::make('title') // 関連するフォルダーの title を表示
                ->label(__('ledger.folder.title')), // カラムのラベル
                TextColumn::make('notificationType.name') // 通知タイプを表示
                ->label(__('ledger.notification_type'))
                    ->formatStateUsing(function ($record) {
                        //                dd($record);
                        return $record->notificationType ? $record->notificationType->name : null;
                    }), IconColumn::make('permission')
                    ->label(__('ledger.notify'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(function (Folder $record): bool {
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
                        SelectTree::make('recordId')
                            ->searchable()
                            ->label(__('ledger.folder.title'))
                            ->relationship('parent', 'title', 'parent_id')
                            ->required()
                            ->withCount()
                            ->enableBranchNode()
                            ->defaultOpenLevel(10),
                        CheckboxList::make('notification_type_ids') // 変更: 複数選択可能な CheckboxList
                        ->label(__('ledger.notification_types'))
                            ->options(NotificationType::pluck('name', 'id')->toArray()) // 通知タイプを選択
                            ->columns(3)
                            ->default(function () {
                                // デフォルトで、Ledger 関連の通知タイプをすべて選択
                                return NotificationType::where('name', 'like', 'ledger_%')->pluck('id')->toArray();
                            })
                            ->required(),

                        Hidden::make('recordId')
                            ->default('null'),

                    ])
//                    ->translateLabel()
                    ->using(function (array $data, string $model): ?Model {
                        if (is_null($data['recordId'])) {
                            return null;
                        }

                        $role = $this->getOwnerRecord();
                        $folderId = $data['recordId'];
                        $modifierId = auth()->id();
                        $selectedNotificationTypeIds = $data['notification_type_ids'];

                        $notificationTypeIds = NotificationType::pluck('id')->toArray();

                        // 既存の通知設定を取得 (permission が NOTIFY_ON または NOTIFY_OFF のもの)
                        $existingSettings = RoleFolderPermission::where('role_id', $role->id)
                            ->where('folder_id', $folderId)
                            ->whereIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
                            ->get();

                        // 選択された通知タイプについて、role_folder_permissions レコードを作成/更新
                        foreach ($notificationTypeIds as $notificationTypeId) {

                            if (in_array($notificationTypeId, $selectedNotificationTypeIds)) {
                                $switch = FolderPermissionType::NOTIFY_ON;
                            } else {
                                $switch = FolderPermissionType::NOTIFY_OFF;
                            }

                            $setting = $existingSettings->where('notification_type_id', $notificationTypeId)->first();
                            if ($setting) {
                                // 既存の設定がある場合は、permission を NOTIFY_ON に更新
                                $setting->update([
                                    'modifier_id' => $modifierId,
                                    'permission' => $switch,
                                ]);
                            } else {
                                // 既存の設定がない場合は、新規作成
                                $createdRecord = RoleFolderPermission::create([
                                    'role_id' => $role->id,
                                    'folder_id' => $folderId,
                                    'notification_type_id' => $notificationTypeId,
                                    'modifier_id' => $modifierId,
                                    'permission' => $switch,
                                ]);
                                //                                dd($setting,$notificationTypeId,$createdRecord);
                            }
                        }

                        return null; // AttachAction では通常、何も返さない
                    }),
            ])->actions([
                DeleteAction::make(),
                //                DisableNotification::make(), // 追加
                EditAction::make() // 追加: EditAction を追加
                ->form([
                    CheckboxList::make('notification_types') // 変更: CheckboxList を追加
                    ->label(__('ledger.notification_types'))
                        ->options(NotificationType::pluck('name', 'id')->toArray()) // 通知タイプを選択
                        ->columns(3)
                        ->afterStateHydrated(function (CheckboxList $component, Folder $record) {
                            //                            dd($record);
                            $selected = RoleFolderPermission::where('role_id', $record->role_id)
                                ->where('folder_id', $record->folder_id)
                                ->where('permission', FolderPermissionType::NOTIFY_ON->value)
                                ->pluck('notification_type_id')->toArray();
                            // dd($record, $selected);
                            $component->state($selected);
                        }),
                    //                        ->dehydrated(false), // フォーム送信時に状態を保存しない
                ])
                    ->using(function (Model $record, array $data): Model {
                        //                        dd($data,$record);
                        // 通知設定を更新

                        $folderId = $record->id;
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

            ])->bulkActions([
                //
            ]);
    }

    public function form(Form $form): Form
    {
        // この RelationManager ではフォームは使わない
        return $form->schema([]);
    }
}
