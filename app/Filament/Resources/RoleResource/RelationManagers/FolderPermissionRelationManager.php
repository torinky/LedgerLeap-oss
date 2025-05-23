<?php

// ★ Namespace を適切に変更
namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Enums\FolderPermissionType;

use App\Models\Folder;

use App\Models\RoleFolderPermission;
use CodeWithDennis\FilamentSelectTree\SelectTree;

// ★ SelectTree を使用
use Filament\Forms\Components\CheckboxList;

// ★ CheckboxList を使用
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;

use Filament\Tables\Actions\BulkActionGroup;

// ★ CreateAction を使用
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;

// ★ EditAction を使用
use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;

// ★ クラス名を変更
class FolderPermissionRelationManager extends RelationManager
{
    // ★ リレーションシップ名を roleFolderPermissions に変更
    protected static string $relationship = 'roleFolderPermissions';

    // ★ レコードタイトル属性は使わない
    // protected static ?string $recordTitleAttribute = null;

    // ★ getRecordTitle をオーバーライドして Folder 名を表示
    public function getRecordTitle(Model $record): ?string
    {
        // $record は RoleFolderPermission インスタンス
        return $record->folder?->title ?? __('Unknown Folder');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        // ★ タイトルを変更
        return __('permission.folder_permissions');
    }

    protected static function getModelLabel(): string
    {
        // ★ ラベルを変更
        return __('permission.folder_permissions');
    }

    protected static function getPluralModelLabel(): string
    {
        // ★ ラベルを変更
        return __('permission.folder_permissions');
    }

    // ★ 権限編集用のモーダルフォームを定義
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ★ フォルダ名は表示専用 (モーダルヘッダーで代替しても良い)
                // Placeholder::make('folder_name')...

                // ★ CheckboxList でアクセス権限を選択
                CheckboxList::make('permissions')
                ->label(__('permission.access_permissions'))
                ->options(FolderPermissionType::asAccessSelectArray()) // アクセス権限のみ
                ->live()
                ->afterStateUpdated(function (\Livewire\Component $livewire, CheckboxList $component, ?array $state) {
                    $newState = $this->applyPermissionHierarchy($state ?? []);
                    $component->state($newState);
                })
                ->columns(2)
                ->bulkToggleable()
                // ->required() // 権限なしを許容する場合
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('permission.folder_permissions'))
            // ★ クエリでアクセス権限レコードのみをフィルタリング
            ->query(function (EloquentBuilder $query) {
                $query->setModel(new RoleFolderPermission());
                // アクセス権限タイプのみに絞り込み
                $query->whereIn('permission', FolderPermissionType::accessPermissionValues());
                // Folder リレーションを Eager Loading
                $query->with(['folder:id,title']);
                return $query;
            })
            // ★ Grouping を追加してフォルダごとに権限を表示 (推奨)
            ->defaultGroup('folder.title') // Folder タイトルでグループ化
            ->groups([
                \Filament\Tables\Grouping\Group::make('folder.title')
                    ->label(__('ledger.folder.title'))
                    ->collapsible(), // 折りたたみ可能に
            ])
            ->columns([
                // ★ Folder タイトルはグループヘッダーで表示されるため、カラムとしては不要になる場合がある
                TextColumn::make('folder.title')
                    ->label(__('フォルダ名'))
                    ->searchable(isIndividual: true) // 個別検索のみ
                    ->sortable()
                ,
                // ★ 設定されているアクセス権限を表示 (Enum のラベルを使用)
                TextColumn::make('permission')
                    ->label(__('permission.title'))
                    ->badge()
                    ->color(fn(?FolderPermissionType $state): string => $state?->getColor() ?? 'gray')
                    ->formatStateUsing(fn(?FolderPermissionType $state): string => $state?->getLabel() ?? '-')
                    ->searchable() // permission の value で検索
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('create')
                ->label(__('permission.attach_folder_permissions'))
                ->modalHeading(__('permission.attach_folder_modal_heading')) // モーダルタイトル
                ->form([ // Create 用のフォーム
                    SelectTree::make('folder_id')
                        ->label(__('ledger.folder.title'))
                        ->relationship(relationship: 'folder', titleAttribute: 'title', parentAttribute: 'parent_id',
                            modifyQueryUsing: fn(EloquentBuilder $query) => $query->orderBy('_lft'))
                        ->required()
                        ->searchable()
                        ->enableBranchNode()
                        ->defaultOpenLevel(1),
                    CheckboxList::make('permissions') // 権限は複数選択
                    ->label(__('permission.access_permissions'))
                    ->options(FolderPermissionType::asAccessSelectArray())
                        ->live()
                        ->afterStateUpdated(function (\Livewire\Component $livewire, CheckboxList $component, ?array $state) {
                            $newState = $this->applyPermissionHierarchy($state ?? []);
                            $component->state($newState);
                        })
                        ->columns(2)
                        ->bulkToggleable()
                        // ->required()
                ])
                ->action(
                    function (array $data): void {
                        // 複数レコードを作成
                        // ★ データ保存処理を action() メソッド内に実装
                        $role = $this->getOwnerRecord();
                        $folderId = $data['folder_id'];
                        $permissionsToCreate = $this->applyPermissionHierarchy($data['permissions'] ?? []);
                        $modifierId = auth()->id();
                        $now = now();

                        try {
                            DB::transaction(function () use ($role, $folderId, $permissionsToCreate, $modifierId, $now) {
                                // 既存のアクセス権限を削除
                                RoleFolderPermission::where('role_id', $role->id)
                                    ->where('folder_id', $folderId)
                                    ->whereIn('permission', FolderPermissionType::accessPermissionValues())
                                    ->delete();

                                // 新しい権限レコードを作成
                                $recordsToInsert = [];
                                foreach ($permissionsToCreate as $permissionValue) {
                                    $recordsToInsert[] = [
                                        'role_id' => $role->id,
                                        'folder_id' => $folderId,
                                        'permission' => $permissionValue,
                                        'modifier_id' => $modifierId,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ];
                                }
                                if (!empty($recordsToInsert)) {
                                    RoleFolderPermission::insert($recordsToInsert);
                                }
                            });

                            Notification::make()
                                ->title(__('permission.attach_folder_permissions_success'))
                                ->success()
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
                                ->title(__('permission.attach_folder_permissions_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            Log::error('Failed to create folder permissions: ' . $e->getMessage(), ['data' => $data, 'exception' => $e]);
                        }
                    }),
            ])
            ->actions([
                // ★ 編集アクション: モーダルで権限チェックボックスを表示
                EditAction::make()
                    ->label(__('permission.edit_permission'))
                    ->modalHeading(fn(RoleFolderPermission $record) => __('permission.edit_folder_permission_modal_heading', ['folder' => $record->folder?->title]))
                    // ★ mountUsing で現在の権限をフォームにロード
                    ->mountUsing(function (Form $form, RoleFolderPermission $record) {
                        // 同じフォルダの他の権限レコードも取得する必要がある
                        $role = $this->getOwnerRecord();
                        $currentPermissions = RoleFolderPermission::where('role_id', $role->id)
                            ->where('folder_id', $record->folder_id) // record から folder_id を取得
                            ->whereIn('permission', FolderPermissionType::accessPermissionValues())
                            ->pluck('permission')
                            ->all(); // semicolon
                        $form->fill(['permissions' => $currentPermissions]);
                    })
                    // ★ form() メソッドで定義したフォームスキーマを使用
                    ->form(fn(Form $form) => $this->form($form))
                    // ★ using で保存処理 (Create と同様)
                    ->using(function (Model $record, array $data): Model { // $record は RoleFolderPermission
                        $role = $this->getOwnerRecord();

                        $folderId = $record->folder_id; // record から folder_id を取得
                        $newPermissions = $this->applyPermissionHierarchy($data['permissions'] ?? []);
                        $modifierId = auth()->id();
                        $now = now();

                        try {
                            DB::transaction(function () use ($role, $folderId, $newPermissions, $modifierId, $now) {
                                // 既存のアクセス権限を削除
                                RoleFolderPermission::where('role_id', $role->id)
                                    ->where('folder_id', $folderId)
                                    ->whereIn('permission', FolderPermissionType::accessPermissionValues())
                                    ->delete();
                                // 新しい権限を登録
                                $recordsToInsert = [];
                                foreach ($newPermissions as $permissionValue) {
                                    $recordsToInsert[] = [
                                        'role_id' => $role->id,
                                        'folder_id' => $folderId,
                                        'permission' => $permissionValue,
                                        'modifier_id' => $modifierId,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ];
                                }
                                if (!empty($recordsToInsert)) {
                                    RoleFolderPermission::insert($recordsToInsert);
                                }
                            });
                            Notification::make()
                                ->title(__('permission.update_folder_permissions_success'))
                                ->success()
                                ->send();

                        } catch (Exception $e) {
                            Notification::make()
                                ->title(__('permission.error'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            Log::error('Failed to update folder permissions: ' . $e->getMessage(), ['record_id' => $record->id, 'data' => $data, 'exception' => $e]);
                            // エラーを再スローするかどうか
                        }
                        // 変更されたレコードを返す必要がある場合がある
                        // ただし、複数のレコードが変更されている
                        return $record; // 一旦元のレコードを返す
                    }),
                // ★ 削除アクション: 特定の権限レコードを削除
                DeleteAction::make()
                    ->successNotificationTitle(__('permission.delete_folder_permission_success'))
                    ->failureNotificationTitle(__('permission.delete_folder_permission_failed'))
                    ->using(function (DeleteAction $action, Model $record) {
                        try {
                            $record->delete();
                            // 成功通知
                        } catch (Exception $e) {
                            Log::error('Failed to delete folder permission: ' . $e->getMessage(), ['record_id' => $record->id, 'exception' => $e]);
                            $action->failure();
                        }
                    }),
                // ★ 全権限解除アクション (フォルダ単位)
                Action::make('detach_folder_permissions')
                    ->label(__('permission.detach_folder_permissions'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn(RoleFolderPermission $record) => __('permission.detach_folder_permissions_modal_heading', ['folder' => $record->folder?->title]))
                    ->modalDescription(__('permission.detach_folder_permissions_modal_description'))
                    ->action(function (RoleFolderPermission $record) {
                        $role = $this->getOwnerRecord();
                        RoleFolderPermission::where('role_id', $role->id)
                            ->where('folder_id', $record->folder_id) // record から folder_id
                            ->whereIn('permission', FolderPermissionType::accessPermissionValues())
                            ->delete();
                        Notification::make()
                            ->title(__('permission.detach_folder_permissions_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // ★ 一括削除アクション (選択された権限レコードを削除)
                    DeleteBulkAction::make()
                        ->successNotificationTitle(__('permission.bulk_delete_folder_permissions_success'))
                        ->failureNotificationTitle(__('permission.bulk_delete_folder_permissions_failed'))
                        ->using(function (DeleteBulkAction $action, EloquentCollection $records) {
                            try {
                                $records->each->delete();
                                // 成功通知
                            } catch (Exception $e) {
                                Log::error('Failed to bulk delete folder permissions: ' . $e->getMessage(), [
                                    'record_ids' => $records->pluck('id')->toArray(),
                                    'exception' => $e,
                                ]);
                                $action->failure();
                            }
                        }),
                ]),
            ]);
    }

    /**
     * 選択された権限に、包含関係に基づいて下位の権限を追加するヘルパーメソッド
     */
    protected function applyPermissionHierarchy(array $selectedPermissions): array
    {
        $finalPermissions = $selectedPermissions;
//        $finalPermissionsEnums = [];
        foreach ($selectedPermissions as $permissionKey => $permissionValue) {
            if ($permissionValue instanceof FolderPermissionType) {
                $permissionEnum = $permissionValue;
                $permissionValue = $permissionValue->value;
                $finalPermissions[$permissionKey] = $permissionValue;
            } else {
                $permissionEnum = FolderPermissionType::tryFrom($permissionValue);
            }
//            $finalPermissionsEnums[$permissionValue] = $permissionEnum;

            if ($permissionEnum && isset(FolderPermissionType::HIERARCHY[$permissionValue])) {
                $finalPermissions = array_merge($finalPermissions, FolderPermissionType::HIERARCHY[$permissionValue]);
            }
        }
//        return $finalPermissionsEnums;
        // Filter out notification types just in case
        $accessOnlyPermissions = array_filter($finalPermissions, function ($p) {
            $enum = FolderPermissionType::tryFrom($p);
            return $enum && $enum->isAccessType();
        });
        return array_unique($accessOnlyPermissions);
    }
}