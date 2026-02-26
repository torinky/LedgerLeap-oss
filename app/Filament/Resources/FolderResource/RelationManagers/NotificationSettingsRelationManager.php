<?php

namespace App\Filament\Resources\FolderResource\RelationManagers;

use App\Enums\FolderPermissionType;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationSettingsRelationManager extends RelationManager
{
    // 1. リレーションシップを中間テーブルモデルを直接返すものに変更
    protected static string $relationship = 'roleFolderPermissions';

    // ★★★ この行を追加 ★★★
    protected static ?string $model = RoleFolderPermission::class;

    // 2. レコードタイトル属性は使わない (RoleFolderPermissionにnameはない)
    protected static ?string $recordTitleAttribute = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ledger.folder.notification');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.folder.notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ledger.folder.notification');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('ledger.folder.notification'))
            // 3. クエリを定義し、必要なリレーションをEager Loadする
            ->query(function (Builder $query) {
                // 通知設定に関連するレコードのみを対象とする
                return $this->getRelationship()->getRelated()->newQuery()
                    ->whereIn('permission', [
                        FolderPermissionType::NOTIFY_ON,
                        FolderPermissionType::NOTIFY_OFF,
                    ])->with(['role:id,name', 'notificationType:id,name']);
            })
            ->columns([
                // 4. カラム定義をリレーション先のプロパティを使うように変更
                // $record は RoleFolderPermission インスタンスになる
                TextColumn::make('role.name')
                    ->label(__('role.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('notificationType.name')
                    ->label(__('ledger.notification_type'))
                    ->formatStateUsing(fn (?string $state) => $state ? __('ledger.notification_types.'.$state, [], $state) : '')
                    ->searchable()
                    ->sortable(),

                // 5. ToggleColumnを修正。$recordはRoleFolderPermissionなので直接permissionを操作できる
                ToggleColumn::make('permission')
                    ->label(__('ledger.notify'))
                    ->onIcon('heroicon-o-check-badge')
                    ->offIcon('heroicon-o-x-mark')
                    ->onColor('success')
                    ->offColor('danger')
                    // 初期状態をboolで返す
                    ->getStateUsing(fn (RoleFolderPermission $record): bool => $record->permission === FolderPermissionType::NOTIFY_ON)
                    // 更新処理
                    ->updateStateUsing(function (RoleFolderPermission $record, bool $state) {
                        try {
                            $record->update([
                                'permission' => $state ? FolderPermissionType::NOTIFY_ON : FolderPermissionType::NOTIFY_OFF,
                                'modifier_id' => auth()->id(),
                            ]);
                            Notification::make()->title(__('ledger.notification.updated_success'))->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title(__('ledger.notification.updated_error'))->danger()->send();
                            // UIを元に戻すためにリフレッシュ
                            $this->js('window.location.reload()');
                        }
                    }),
            ])
            ->filters([
                // 必要に応じてフィルタを再設定
            ])
            ->headerActions([
                // 6. AttachActionをカスタムのActionに置き換える
                Action::make('create_notification_setting')
                    ->label(__('ledger.new_relation_attach'))
                    ->icon('heroicon-o-link')
                    ->form([
                        Select::make('role_id')
                            ->label(__('ledger.role'))
                            ->options(Role::pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        CheckboxList::make('notification_type_ids')
                            ->label(__('ledger.notification_type'))
                            ->options(fn () => NotificationType::all()->mapWithKeys(fn ($type) => [$type->id => __('ledger.notification_types.'.$type->name, [], $type->name)]))
                            ->columns(3)
                            ->required(),
                        Toggle::make('permission')
                            ->label(__('ledger.notify'))
                            ->default(true),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $folder = $livewire->getOwnerRecord();
                        $modifierId = auth()->id();
                        $permission = $data['permission'] ? FolderPermissionType::NOTIFY_ON : FolderPermissionType::NOTIFY_OFF;

                        DB::transaction(function () use ($folder, $data, $permission, $modifierId) {
                            foreach ($data['notification_type_ids'] as $notificationTypeId) {
                                RoleFolderPermission::updateOrCreate(
                                    [
                                        'folder_id' => $folder->id,
                                        'role_id' => $data['role_id'],
                                        'notification_type_id' => $notificationTypeId,
                                    ],
                                    [
                                        'permission' => $permission,
                                        'modifier_id' => $modifierId,
                                    ]
                                );
                            }
                        });

                        Notification::make()->title(__('ledger.notification.created_success'))->success()->send();
                    }),
            ])
            ->actions([
                Action::make('edit_notification_setting')
                    ->label(__('ledger.edit'))
                    ->icon('heroicon-o-pencil')
                    ->fillForm(function (Model $record): array {
                        // $record は RoleFolderPermission のインスタンス
                        $folder = $this->getOwnerRecord();
                        $roleId = $record->role_id;
                        $existingPermissions = RoleFolderPermission::where('folder_id', $folder->id)
                            ->where('role_id', $roleId)
                            ->whereIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
                            ->pluck('notification_type_id')
                            ->toArray();

                        return [
                            'role_id' => $roleId,
                            'notification_type_ids' => $existingPermissions,
                            'permission' => $record->permission === FolderPermissionType::NOTIFY_ON,
                        ];
                    })
                    ->form([
                        Select::make('role_id')
                            ->label(__('ledger.role'))
                            ->options(Role::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->disabled(), // ロールは変更不可
                        CheckboxList::make('notification_type_ids')
                            ->label(__('ledger.notification_type'))
                            ->options(fn () => NotificationType::all()->mapWithKeys(fn ($type) => [$type->id => __('ledger.notification_types.'.$type->name, [], $type->name)]))
                            ->columns(3)
                            ->required(),
                        Toggle::make('permission')
                            ->label(__('ledger.notify'))
                            ->default(true),
                    ])
                    ->action(function (Model $record, array $data, RelationManager $livewire): void {
                        $folder = $livewire->getOwnerRecord();
                        $modifierId = auth()->id();
                        $selectedNotificationTypeIds = $data['notification_type_ids'];
                        $roleId = $record->role_id;
                        $permissionStatus = $data['permission'] ? FolderPermissionType::NOTIFY_ON : FolderPermissionType::NOTIFY_OFF;

                        DB::transaction(function () use ($folder, $roleId, $selectedNotificationTypeIds, $permissionStatus, $modifierId) {
                            // 既存の通知設定を取得
                            $existingNotificationSettings = RoleFolderPermission::where('folder_id', $folder->id)
                                ->where('role_id', $roleId)
                                ->whereIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
                                ->get();

                            // 選択された通知タイプを更新または作成
                            foreach ($selectedNotificationTypeIds as $notificationTypeId) {
                                RoleFolderPermission::updateOrCreate(
                                    [
                                        'folder_id' => $folder->id,
                                        'role_id' => $roleId,
                                        'notification_type_id' => $notificationTypeId,
                                    ],
                                    [
                                        'permission' => $permissionStatus,
                                        'modifier_id' => $modifierId,
                                    ]
                                );
                            }

                            // 選択されなかった通知タイプを削除
                            foreach ($existingNotificationSettings as $setting) {
                                if (! in_array($setting->notification_type_id, $selectedNotificationTypeIds)) {
                                    $setting->delete();
                                }
                            }
                        });

                        Notification::make()->title(__('ledger.notification.updated_success'))->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }
}
