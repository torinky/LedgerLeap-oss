<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Enums\FolderPermissionType;

// ★ DeleteNotification アクションの修正が必要になる可能性
// use App\Filament\Tables\Actions\DeleteNotification;
use App\Models\Folder;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\RoleFolderPermission;

// ★ 使用するモデル
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;

// ★ Toggle を使う方がシンプル
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;

// ★ カスタムアクション用に Action を使う
// use Filament\Tables\Actions\AttachAction; // ★ AttachAction は使わない
use Filament\Tables\Actions\BulkActionGroup;

//use Filament\Tables\Actions\CreateAction;

// ★ CreateAction を使う
use Filament\Tables\Actions\DeleteAction;

// ★ 標準の DeleteAction を使う
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Exception;

// ★ 例外処理用
use Filament\Notifications\Actions\Action as NotificationAction;

// ★ 通知内のアクション用 (オプション)
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

// ★ BulkAction用
use Illuminate\Support\Facades\Log;

// ★ ログ出力用
class NotificationSettingsRelationManager extends RelationManager
{
    // ★ リレーションシップ名を RoleFolderPermission に変更
    protected static string $relationship = 'roleFolderPermissions';

    // ★ レコードタイトル属性は使わず、getRecordTitle をオーバーライド
    // protected static ?string $recordTitleAttribute = null; // 不要

    // ★ getRecordTitle をオーバーライドして Folder 名と通知タイプ名を表示
    public function getRecordTitle(Model $record): ?string
    {
        // $record は RoleFolderPermission インスタンス
        // Eager Loading されていることを期待
        $folderTitle = $record->folder?->title ?? __('Unknown Folder');
        $notificationTypeName = $record->notificationType?->name ?? __('Unknown Type');
        $translatedTypeName = __('ledger.notification_types.' . $notificationTypeName, [], $notificationTypeName); // 翻訳、なければ元の名前

        return $folderTitle . ' - ' . $translatedTypeName;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ledger.folder.notification');
    }

    // ★ モデルラベルは現状維持でも良い
    protected static function getModelLabel(): string
    {
        return __('ledger.folder.notification');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('ledger.folder.notification');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('ledger.folder.notification'))
            // ->recordTitleAttribute(...) // 不要
            ->query(function (EloquentBuilder $query) {
                $query->setModel(new RoleFolderPermission());

                // ★ 通知関連の権限のみをフィルタリング
                $query->whereIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF]);

                // ★ 必要なリレーションを Eager Loading
                $query->with(['folder:id,title', 'notificationType:id,name']);

                return $query;
            })
            ->defaultGroup('folder.title') // Folder タイトルでグループ化
            ->groups([
                \Filament\Tables\Grouping\Group::make('folder.title')
                    ->label(__('ledger.folder.title'))
                    ->collapsible(), // 折りたたみ可能に
            ])
            ->columns([
                // ★ Folder のタイトルを表示 (リレーション経由)
                TextColumn::make('folder.title')
                    ->label(__('ledger.folder.title'))
//                    ->searchable(isIndividual: true, isGlobal: true) // 個別検索とグローバル検索を有効化
                    ->sortable()
                , // 基本的なソートを有効化 (Joinなし)

                // ★ 通知タイプの表示 (リレーション経由)
                TextColumn::make('notificationType.name')
                    ->label(__('ledger.notification_type'))
                    ->formatStateUsing(function (?string $state) { // $state は notificationType.name
                        if ($state) {
                            $labelKey = 'ledger.notification_types.' . $state;
                            // 翻訳が存在するか確認し、存在すれば翻訳、なければ元の名前を使用
                            return trans()->has($labelKey) ? __($labelKey) : $state;
                        }
                        return __('N/A');
                    })
//                    ->searchable(isIndividual: true, isGlobal: true) // 個別検索とグローバル検索を有効化
                    ->sortable(), // 基本的なソートを有効化 (Joinなし)

                // ★ 通知 ON/OFF の表示 (RoleFolderPermission の permission 属性を直接使用)
                /*                IconColumn::make('permission')
                                    ->label(__('ledger.notify'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-badge')
                                    ->falseIcon('heroicon-o-x-mark')
                                    ->trueColor('success')
                                    ->falseColor('danger')
                                    // Enum キャストがあればこれだけで動作するはず
                                    ->getStateUsing(fn(RoleFolderPermission $record): bool => $record->permission === FolderPermissionType::NOTIFY_ON),*/

                // --- 通知 ON/OFF を ToggleColumn に変更 ---
                ToggleColumn::make('permission_toggle') // <<<--- ToggleColumn に変更
                ->label(__('ledger.notify'))
                    // ON / OFF に対応する Enum ケースを定義
                    ->onColor('success')
                    ->offColor('danger')
                    ->onIcon('heroicon-o-check-badge')
                    ->offIcon('heroicon-o-x-mark')
                    // ここで状態を boolean (ONかどうか) に変換する必要はないはず
                    // ToggleColumn は通常 boolean カラムに使うが、
                    // カスタムロジックで Enum を扱えるか試す価値あり
                    // もし Enum でうまく動かなければ、 boolean の仮想カラムを作るなど工夫が必要
                    ->updateStateUsing(function (RoleFolderPermission $record, $state): void { // <<<--- 更新ロジック
                        // $state にはトグル後の状態 (true / false) が入る
                        $newPermission = $state ? FolderPermissionType::NOTIFY_ON : FolderPermissionType::NOTIFY_OFF;
                        try {
                            $record->update([
                                'permission' => $newPermission,
                                'modifier_id' => auth()->id(),
                            ]);
                            Notification::make()
                                ->title(__('ledger.notification.updated_success'))
                                ->success()
                                ->send();
                        } catch (Exception $e) {
                            Log::error('Failed to update notification setting via toggle: ' . $e->getMessage(), ['record_id' => $record->id]);
                            Notification::make()
                                ->title(__('ledger.notification.updated_error'))
                                ->danger()
                                ->send();
                            // 状態を元に戻す (オプション)
                            $this->refresh(); // テーブル再描画で元の状態に戻ることを期待
                        }
                    })
                    // <<<--- 初期状態の設定 (重要)
                    // ToggleColumn は内部的に boolean を期待するため、Enum から boolean への変換が必要
                    ->getStateUsing(fn(RoleFolderPermission $record): bool => $record->permission === FolderPermissionType::NOTIFY_ON),

            ])
            ->filters([
                Filter::make('folder_id')
                    ->form([
                        Select::make('value')
                            ->label(__('ledger.folder.title'))
                            ->relationship('folder', 'title')
                            ->searchable()
                            ->multiple()
                            ->preload(),
                    ])
                    ->query(function (EloquentBuilder $query, array $data): EloquentBuilder {
                        if (blank($data['value'])) {
                            return $query;
                        }
                        return $query->whereIn('folder_id', $data['value']);
                    })
                    ->label(__('ledger.folder.title')),

                Filter::make('notification_type_id')
                    ->form([
                        Select::make('value')
                            ->label(__('ledger.notification_type'))
                            ->options(function () {
                                // 日本語のラベルでオプションを生成
                                return NotificationType::all()->mapWithKeys(function ($type) {
                                    $label = __('ledger.notification_types.' . $type->name, [], $type->name);
                                    return [$type->id => $label];
                                })->all();
                            })
                            ->searchable() // 日本語ラベルで検索可能にする
                            ->multiple(),
                    ])
                    ->query(function (EloquentBuilder $query, array $data): EloquentBuilder {
                        if (blank($data['value'])) {
                            return $query;
                        }
                        return $query->whereIn('notification_type_id', $data['value']);
                    })
                    ->label(__('ledger.notification_type')),
                // 必要に応じてフィルタを追加
            ])
            ->headerActions([
                // ★ CreateAction の代わりにカスタム Action を使用
                Action::make('create') // アクション名を 'create' に設定
                ->label(__('ledger.new_relation_attach'))
                    ->form([ // フォーム定義をここに移動
                        SelectTree::make('folder_id')
                            ->label(__('ledger.folder.title'))
                            ->relationship(relationship: 'folder', titleAttribute: 'title', parentAttribute: 'parent_id', modifyQueryUsing: fn(EloquentBuilder $query) => $query->orderBy('_lft'))
                            ->required()
                            ->searchable()
                            ->enableBranchNode()
                            ->defaultOpenLevel(1),
                        CheckboxList::make('notification_type_ids')
                            ->label(__('ledger.notification_type'))
                            ->options(function () {
                                return NotificationType::all()->mapWithKeys(function ($notificationType) {
                                    $labelKey = 'ledger.notification_types.' . $notificationType->name;
                                    return [$notificationType->id => trans()->has($labelKey) ? __($labelKey) : $notificationType->name];
                                })->toArray();
                            })
                            ->columns(3)
                            ->default(function () {
                                return NotificationType::where('default_notify', true)->pluck('id')->toArray();
                            })
                            ->required(),
                        Toggle::make('notify_on')
                            ->label(__('ledger.notify'))
                            ->default(true)
                            ->required(),
                    ])
                    ->action(function (array $data): void { // ★ データ保存処理を action() メソッド内に実装
                        $role = $this->getOwnerRecord(); // RelationManager 内なので $this でアクセス可能
                        $folderId = $data['folder_id'];
                        $notificationTypeIds = $data['notification_type_ids'];
                        $permission = $data['notify_on'] ? FolderPermissionType::NOTIFY_ON : FolderPermissionType::NOTIFY_OFF;
                        $modifierId = auth()->id();
                        try {

                            DB::transaction(function () use ($role, $folderId, $notificationTypeIds, $permission, $modifierId) {
                                foreach ($notificationTypeIds as $notificationTypeId) {
                                    RoleFolderPermission::updateOrCreate(
                                        [
                                            'role_id' => $role->id,
                                            'folder_id' => $folderId,
                                            'notification_type_id' => $notificationTypeId,
                                        ],
                                        [
                                            'permission' => $permission,
                                            'modifier_id' => $modifierId,
                                        ]
                                    );
                                }
                            });
                            // ★ 成功通知
                            Notification::make()
                                ->title(__('ledger.notification.created_success'))
                                ->success()
                                ->send();

                        } catch (Exception $e) {
                            // ★ 失敗通知
                            Notification::make()
                                ->title(__('ledger.notification.created_error'))
                                ->body($e->getMessage()) // エラーメッセージを表示 (デバッグ用、本番では削除または簡略化推奨)
                                ->danger()
                                ->send();

                            // ★ エラーログ
                            Log::error('Failed to create notification settings: ' . $e->getMessage(), [
                                'role_id' => $role->id,
                                'folder_id' => $folderId,
                                'notification_type_ids' => $notificationTypeIds,
                                'exception' => $e,
                            ]);
                        }
                    }),


            ])
            ->actions([
                // ★ EditAction の修正 (通知 ON/OFF の切り替えのみ)
                /*                EditAction::make()
                                    ->label(__('Edit'))
                                    ->form([
                                        Toggle::make('permission')
                                            ->label(__('ledger.notify'))
                                            ->onIcon('heroicon-o-check-badge')
                                            ->offIcon('heroicon-o-x-mark')
                                            ->onColor('success')
                                            ->offColor('danger')
                                            // RoleFolderPermission の permission 属性を boolean に変換して設定
                                            ->afterStateHydrated(function (Toggle $component, RoleFolderPermission $record) {
                                                $component->state($record->permission === FolderPermissionType::NOTIFY_ON);
                                            })
                                            // 送信時に boolean から Enum/値 に変換
                                            ->dehydrateStateUsing(fn(bool $state): string => ($state ? FolderPermissionType::NOTIFY_ON : FolderPermissionType::NOTIFY_OFF)->value)
                                            ->required(),
                                    ])
                                    ->using(function (Model $record, array $data): Model {
                                        try {
                                            $record->update([
                                                'permission' => $data['permission'],
                                                'modifier_id' => auth()->id(),
                                            ]);

                                            // ★ 成功通知 (using 内で送信)
                                            Notification::make()
                                                ->title(__('ledger.notification.updated_success'))
                                                ->success()
                                                ->sendToDatabase(auth()->user()); // 必要に応じてDBにも保存

                                            return $record;

                                        } catch (Exception $e) {
                                            // ★ 失敗通知 (using 内で送信)
                                            Notification::make()
                                                ->title(__('ledger.notification.updated_error'))
                                                ->body($e->getMessage()) // デバッグ用
                                                ->danger()
                                                ->sendToDatabase(auth()->user()); // 必要に応じてDBにも保存

                                            // ★ エラーログ
                                            Log::error('Failed to update notification setting: ' . $e->getMessage(), [
                                                'record_id' => $record->id,
                                                'data' => $data,
                                                'exception' => $e,
                                            ]);

                                            // 例外を再スローするか、null を返すなどして Filament にエラーを伝える
                                            // ここでは例外を再スローして標準のエラー処理に任せる
                                            throw $e;
                                        }
                                    }),
                */
                // ★ 標準の DeleteAction を使用 (RoleFolderPermission レコードを削除)
                DeleteAction::make()
                    // ★ 成功・失敗通知タイトルを設定
                    ->successNotificationTitle(__('ledger.notification.deleted_success'))
                    ->failureNotificationTitle(__('ledger.notification.deleted_error'))
                    // ★ 削除前後の処理 (エラーログなど)
                    ->before(function (DeleteAction $action, Model $record) {
                        // 削除前の処理が必要な場合
                    })
                    ->after(function (Model $record) {
                        // 削除成功後の処理が必要な場合
                    })
                    // ★ using を使ってエラーハンドリングを強化
                    ->using(function (DeleteAction $action, Model $record) {
                        try {
                            $record->delete();
                            // 成功通知は ->successNotificationTitle で設定済み
                        } catch (Exception $e) {
                            // ★ エラーログ
                            Log::error('Failed to delete notification setting: ' . $e->getMessage(), [
                                'record_id' => $record->id,
                                'exception' => $e,
                            ]);
                            // 失敗通知は ->failureNotificationTitle で設定済み
                            // アクションを失敗させるために false を返すか例外をスロー
                            $action->failure(); // これで失敗通知が表示される
                            // または throw $e;
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // ★ 標準の Bulk DeleteAction を使用
                    DeleteBulkAction::make()
                        // ★ 成功・失敗通知タイトルを設定
                        ->successNotificationTitle(__('ledger.notification.bulk_deleted_success'))
                        ->failureNotificationTitle(__('ledger.notification.bulk_deleted_error'))
                        // ★ 削除前後の処理 (エラーログなど)
                        ->before(function (DeleteBulkAction $action, EloquentCollection $records) {
                            // 一括削除前の処理
                        })
                        ->after(function (EloquentCollection $records) {
                            // 一括削除成功後の処理
                        })
                        // ★ using を使ってエラーハンドリングを強化
                        ->using(function (DeleteBulkAction $action, EloquentCollection $records) {
                            try {
                                // トランザクション内で削除を実行
                                DB::transaction(function () use ($records) {
                                    $records->each->delete();
                                });
                                // 成功通知は ->successNotificationTitle で設定済み
                            } catch (Exception $e) {
                                // ★ エラーログ
                                Log::error('Failed to bulk delete notification settings: ' . $e->getMessage(), [
                                    'record_ids' => $records->pluck('id')->toArray(),
                                    'exception' => $e,
                                ]);
                                // 失敗通知は ->failureNotificationTitle で設定済み
                                $action->failure(); // これで失敗通知が表示される
                            }
                        }),]),
            ]);
    }

    // form() メソッドは空のままで良い
    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    // --- 権限チェック ---
    // 操作対象が RoleFolderPermission になるため、必要に応じて Policy を作成・調整してください。
    // ここでは、簡単のため Role に対する権限で代用しています。

    public function canViewAny(): bool
    {
        // return auth()->user()->can('viewAny', RoleFolderPermission::class);
        return auth()->user()->can('viewAny', Role::class); // Role の権限で代用
    }

    public function canCreate(): bool
    {
        // return auth()->user()->can('create', RoleFolderPermission::class);
        return auth()->user()->can('create', Role::class); // Role の権限で代用
    }

    public function canEdit(Model $record): bool // $record は RoleFolderPermission
    {
        // return auth()->user()->can('update', $record); // RoleFolderPermissionPolicy が必要
        return auth()->user()->can('update', $this->getOwnerRecord()); // Role の権限で代用
    }

    public function canDelete(Model $record): bool // $record は RoleFolderPermission
    {
        // return auth()->user()->can('delete', $record); // RoleFolderPermissionPolicy が必要
        return auth()->user()->can('delete', $this->getOwnerRecord()); // Role の権限で代用
    }

    public function canDeleteAny(): bool
    {
        // return auth()->user()->can('deleteAny', RoleFolderPermission::class);
        return auth()->user()->can('deleteAny', Role::class); // Role の権限で代用
    }
}