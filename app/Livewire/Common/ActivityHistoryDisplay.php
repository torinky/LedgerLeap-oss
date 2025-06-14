<?php

namespace App\Livewire\Common;

use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityHistoryDisplay extends Component
{
    use WithPagination;

    // リソースタイプとIDが指定されない場合、全件表示モードとなる
    public ?int $resourceId = null;
    public ?string $resourceType = null; // 'Ledger', 'LedgerDefine', 'Folder'
    public bool $includeRelatedResources = false; // レコードのアクティビティ表示時に、親の台帳定義とフォルダのアクティビティも含めるか
    public string $paginationTheme = 'mary';

    // フィルタリング用プロパティ (MVPでは非実装だが定義しておく)
    public ?string $filterCauserName = null;
    public ?string $filterEventType = null;
    public ?string $filterStartDate = null;
    public ?string $filterEndDate = null;
    public ?string $searchQuery = null;

    /**
     * コンポーネントの初期化
     *
     * @param int|null $resourceId
     * @param string|null $resourceType
     * @param bool $includeRelatedResources
     * @return void
     */
    public function mount(?int $resourceId = null, ?string $resourceType = null, bool $includeRelatedResources = false): void
    {
        $this->resourceId = $resourceId;
        $this->resourceType = $resourceType;
        $this->includeRelatedResources = $includeRelatedResources;
    }

    /**
     * ソート機能 (MVPでは非実装だが定義しておく)
     * @param string $field
     * @return void
     */
    public function sortBy(string $field): void
    {
        // ToDo: ソートロジックを実装
    }

    /**
     * アクティビティログを取得するクエリを構築
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getActivitiesQuery()
    {
        $query = CustomActivity::query()
            ->with(['causer', 'subject']) // 操作者と対象モデルをイーガーロード
            ->orderBy('created_at', 'desc'); // 最新のログが上に来るようにソート

        // 特定のリソースに絞り込む場合
        if ($this->resourceId !== null && $this->resourceType !== null) {
            $subjectTypes = [];
            $subjectIds = [];

            // 基本となるリソースのsubject_typeとsubject_idを設定
            switch ($this->resourceType) {
                case 'Ledger':
                    $subjectTypes[] = Ledger::class;
                    $subjectIds[] = $this->resourceId;
                    break;
                case 'LedgerDefine':
                    $subjectTypes[] = LedgerDefine::class;
                    $subjectIds[] = $this->resourceId;
                    break;
                case 'Folder':
                    $subjectTypes[] = Folder::class;
                    $subjectIds[] = $this->resourceId;
                    break;
                default:
                    // 未知のリソースタイプの場合は空のクエリを返す
                    return $query->whereRaw('0=1');
            }

            // 関連リソースのアクティビティを含める場合
            if ($this->includeRelatedResources) {
                $baseModel = null;
                // モデルの取得と関連リソースIDの追加
                if ($this->resourceType === 'Ledger') {
                    $baseModel = Ledger::find($this->resourceId);
                    if ($baseModel && $baseModel->define) {
                        $subjectTypes[] = LedgerDefine::class;
                        $subjectIds[] = $baseModel->define->id;
                        if ($baseModel->define->folder) {
                            $subjectTypes[] = Folder::class;
                            $subjectIds[] = $baseModel->define->folder->id;
                        }
                    }
                } elseif ($this->resourceType === 'LedgerDefine') {
                    $baseModel = LedgerDefine::find($this->resourceId);
                    if ($baseModel && $baseModel->folder) {
                        $subjectTypes[] = Folder::class;
                        $subjectIds[] = $baseModel->folder->id;
                    }
                }
            }

            // ユニークな subject_type と subject_id の組み合わせでフィルタリング
            $query->where(function ($q) use ($subjectTypes, $subjectIds) {
                $processedTypes = [];
                foreach ($subjectTypes as $index => $type) {
                    if (!in_array($type, $processedTypes)) {
                        $q->orWhere(function ($subQ) use ($type, $subjectIds, $subjectTypes) {
                            $filteredSubjectIdsForType = collect($subjectIds)
                                ->filter(fn($id, $idx) => $subjectTypes[$idx] === $type)
                                ->unique()
                                ->values()
                                ->toArray();
                            $subQ->where('subject_type', $type)
                                ->whereIn('subject_id', $filteredSubjectIdsForType);
                        });
                        $processedTypes[] = $type;
                    }
                }
            });
        }

        // subject_idとsubject_typeの両方がnullのActivityログを除外 (リソースに紐づくアクティビティの文脈では)
        // ただし、ユーザーログイン/ログアウトなどcauser_type/subject_typeがUserでsubject_idがnullのものは含める
        $query->where(function ($q) {
            $q->whereNotNull('subject_id')
                ->orWhere(function ($subQ) {
                    // subject_id が null で、causer_type が User.class の場合 (例: login/logout)
                    $subQ->whereNull('subject_id')
                        ->where('causer_type', User::class);
                });
        });


        // TODO: フィルタリング機能 (filterCauserName, filterEventType, filterStartDate, filterEndDate)
        // TODO: 検索機能 (searchQuery)

        return $query;
    }

    /**
     * コンポーネントのレンダリング
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        // ログ閲覧権限チェック
        if (!auth()->check() || ! auth()->user()->can('viewAny', \App\Models\CustomActivity::class)){
            return view('livewire.common.activity-history-display-no-permission');
        }

        $activities = $this->getActivitiesQuery()->paginate(10);

        return view('livewire.common.activity-history-display', [
            'activities' => $activities,
        ]);
    }

    /**
     * アクティビティの操作内容をユーザーフレンドリーな文字列に変換
     *
     * @param CustomActivity $activity
     * @return string
     */
    public function getOperationDescription(CustomActivity $activity): string
    {
        $subjectName = $this->getSubjectNameForDisplay($activity);

        // event が null で description がある場合 (例: model events generated by spatie activitylog, not custom events)
        $eventKey = $activity->event ?? $activity->description;

        // 特定のイベントタイプを優先して翻訳キーを決定
        if (in_array($eventKey, ['created', 'updated', 'deleted', 'restored', 'forceDeleted'])) {
            $key = "ledger.activity.event.{$activity->subject_type_base}.{$eventKey}";
            if (__($key) !== $key) { // 翻訳キーが存在するか確認
                return __($key, ['resource' => $subjectName]);
            }
            // fallback for generic model events
            return __("ledger.activity.{$eventKey}", ['resource' => $subjectName]);
        }
        // ワークフロー関連のカスタムイベント
        if (in_array($eventKey, ['requested_inspection', 'inspection_completed', 'approved', 'returned_to_draft', 'edited_while_pending', 'task_claimed'])) {
            return __("ledger.activity.workflow.{$eventKey}", ['resource' => $subjectName]);
        }
        // ログイン・ログアウト
        if (in_array($eventKey, ['login', 'logout'])) {
            return __("ledger.activity.event.{$eventKey}", ['resource' => $subjectName]);
        }
        // リレーション操作 (attached/detached)
        if (in_array($eventKey, ['attached', 'detached'])) {
            // 例: organization_user_attached, role_permission_attached など
            // subject_type が Organization で event が 'attached' の場合、
            // 'ledger.activity.event.organization_user_attached' を探す
            $relationEventKey = strtolower(class_basename($activity->subject_type)) . '_' . ($activity->properties->get('relation_name') ?? '') . '_' . $eventKey;
            $key = "ledger.activity.event.{$relationEventKey}";
            if (__($key) !== $key) {
                return __($key, ['resource' => $subjectName, 'related_entity' => $this->getRelatedEntityNameForDisplay($activity)]);
            }

            // Fallback for generic attached/detached if specific relation name not found
            return __("ledger.activity.event.{$eventKey}", ['resource' => $subjectName]);
        }


        // その他のイベント
        $key = "ledger.activity.event.{$eventKey}";
        if (__($key) !== $key) {
            return __($key, ['resource' => $subjectName]);
        }

        // 最終的なフォールバック
        return $activity->description; // fallback to raw description
    }

    /**
     * subject の表示名を取得するヘルパー
     * @param CustomActivity $activity
     * @return string
     */
    protected function getSubjectNameForDisplay(CustomActivity $activity): string
    {
        if (!$activity->subject) {
            // subject が null で causer が User の場合 (例: login/logout) は causer を subject のように扱う
            if ($activity->causer instanceof User) {
                return $activity->causer->name ?? __('ledger.activity.subject.unknown');
            }
            return __('ledger.activity.subject.unknown');
        }

        if ($activity->subject instanceof Ledger) {
            return $activity->subject->define->title ?? ('Ledger ID: ' . $activity->subject->id);
        } elseif ($activity->subject instanceof LedgerDefine) {
            return $activity->subject->title ?? ('LedgerDefine ID: ' . $activity->subject->id);
        } elseif ($activity->subject instanceof Folder) {
            return $activity->subject->title ?? ('Folder ID: ' . $activity->subject->id);
        } elseif ($activity->subject instanceof User) {
            return $activity->subject->name ?? ('User ID: ' . $activity->subject->id);
        } elseif ($activity->subject instanceof Role) {
            return $activity->subject->name ?? ('Role ID: ' . $activity->subject->id);
        } elseif ($activity->subject instanceof Organization) {
            return $activity->subject->name ?? ('Organization ID: ' . $activity->subject->id);
        } elseif ($activity->subject instanceof Permission) { // Permission モデルの場合を追加
            return $activity->subject->name ?? ('Permission ID: ' . $activity->subject->id);
        } elseif ($activity->subject instanceof RoleFolderPermission) { // RoleFolderPermission モデルの場合を追加
            return __('ledger.activity.model_name.role_folder_permission') . ' ID: ' . $activity->subject->id; // 特定の表示名
        }
        return class_basename($activity->subject_type) . ' ID: ' . $activity->subject_id;
    }

    /**
     * リレーション操作時の関連エンティティ名を取得
     * @param CustomActivity $activity
     * @return string
     */
    protected function getRelatedEntityNameForDisplay(CustomActivity $activity): string
    {
        $relationName = $activity->properties->get('relation_name');
        $relatedModelType = $activity->properties->get('related_model_type');
        $relatedModelIds = $activity->properties->get('related_model_ids', []);

        if (empty($relatedModelIds)) {
            return '';
        }

        $relatedEntities = collect();
        if ($relatedModelType) {
            try {
                $relatedModelClass = $relatedModelType;
                if (class_exists($relatedModelClass)) {
                    $relatedEntities = $relatedModelClass::whereIn('id', $relatedModelIds)->get();
                }
            } catch (\Throwable $e) {
                // モデルが見つからない等のエラーは無視
            }
        }

        if ($relatedEntities->isNotEmpty()) {
            return $relatedEntities->pluck('name')->implode(', '); // name プロパティがあればそれを使用
        }

        // Fallback for relation operations when related entity name cannot be found
        if ($relationName) {
            return __('ledger.activity.changes.related_entity', ['relation' => $relationName]);
        }

        return '';
    }

    /**
     * 変更差分を表示するためのフォーマット
     *
     * @param CustomActivity $activity
     * @return string|\Illuminate\Support\HtmlString
     */
    public function formatChanges(CustomActivity $activity)
    {
        if (!$activity->properties->has('attributes') && !$activity->properties->has('old')) {
            return '';
        }

        // $attributes が常にコレクションであることを保証する
        $attributesRaw = $activity->properties->get('attributes');
        $attributes = is_array($attributesRaw) ? collect($attributesRaw) : ($attributesRaw instanceof \Illuminate\Support\Collection ? $attributesRaw : collect());

        $oldRaw = $activity->properties->get('old');
        $old = is_array($oldRaw) ? collect($oldRaw) : ($oldRaw instanceof \Illuminate\Support\Collection ? $oldRaw : collect());

        $changes = collect();
        $isUpdated = $activity->event === 'updated';
        foreach ($attributes as $key => $newValue) {
//            dd($attributes, $old, $key, $newValue);
            $oldValue = $old->get($key);

            // `latest_diff_id`, `updated_at`, `modifier_id`, `created_at`, `creator_id` はスキップ
            if (in_array($key, ['latest_diff_id', 'updated_at', 'modifier_id', 'created_at', 'creator_id'])) {
                continue;
            }
            // `deleted_at` は論理削除で自動的に設定されるため、表示を簡略化
            if ($key === 'deleted_at') {
                if ($newValue !== null && $oldValue === null) {
                    $changes->push(
                        '<strong>' . $key . ':</strong> ' .
                        '<span class="text-green-500">' . __('ledger.activity.changes.attached') . '</span>'
                    );
                } elseif ($newValue === null && $oldValue !== null) {
                    $changes->push(
                        '<strong>' . $key . ':</strong> ' .
                        '<span class="text-red-500 line-through">' . __('ledger.activity.changes.detached') . '</span>'
                    );
                }
                continue;
            }

            // パスワード変更
            if ($key === 'password') {
                $changes->push(
                    '<span class="text-gray-500">' . __('ledger.activity.changes.password_changed') . '</span>'
                );
                continue;
            }

            // JSON文字列のカラム (content, column_define, completed_inspector_role_ids, completed_approver_role_ids)
            // JSON デコードが成功した場合のみ、差分表示を試みるか、「変更あり」と表示
            if (in_array($key, ['content', 'column_define', 'completed_inspector_role_ids', 'completed_approver_role_ids', 'folder_required_roles'])) {
                $isNewValueJsonString = is_string($newValue);
                $isOldValueJsonString = is_string($oldValue);

                $decodedNew = $isNewValueJsonString ? json_decode($newValue, true) : $newValue;
                $newJsonError = $isNewValueJsonString ? json_last_error() : JSON_ERROR_NONE; // 文字列でなければエラーなしとみなす

                $decodedOld = $isOldValueJsonString ? json_decode($oldValue, true) : $oldValue;
                $oldJsonError = $isOldValueJsonString ? json_last_error() : JSON_ERROR_NONE; // 文字列でなければエラーなしとみなす

                // 両方ともJSON文字列としてデコード成功、または最初から配列/nullで、かつ内容が異なる場合
                if (($isNewValueJsonString && $newJsonError === JSON_ERROR_NONE && $isOldValueJsonString && $oldJsonError === JSON_ERROR_NONE) ||
                    (!$isNewValueJsonString && !$isOldValueJsonString) // 両方ともJSON文字列ではない（配列やnullの可能性がある）
                ) {
                    if ($decodedNew !== $decodedOld) {
                        // JSON由来か、元々配列だったかの区別なく、内容が変更されたと表示
                        $changes->push('<strong>' . e($key) . ':</strong> <span class="text-blue-500">' . __('ledger.activity.changes.content_changed') . '</span>');
                    }
                }
                // 一方または両方がJSONデコードに失敗したか、型が異なるが、文字列として比較して異なる場合
                // (例: JSON文字列から非JSON文字列への変更、またはその逆、または単なる文字列の変更)
                // ただし、両方ともJSON文字列ではなく、かつ上記で処理済みの場合はここには来ない
                elseif ((is_string($newValue) || is_null($newValue) || is_bool($newValue)) && (is_string($oldValue) || is_null($oldValue) || is_bool($oldValue)) && (string)$newValue !== (string)$oldValue) {
                    // JSONデコードに失敗した場合や、配列と文字列の比較など、より汎用的な変更表示（文字列、null、booleanの場合のみ）
                    $displayOld = is_array($oldValue) || is_object($oldValue) ? __('ledger.activity.changes.complex_data') : e((string)$oldValue);
                    $displayNew = is_array($newValue) || is_object($newValue) ? __('ledger.activity.changes.complex_data') : e((string)$newValue);

                    // null の場合の表示を調整
                    if (is_null($oldValue)) {
                        $displayOld = '<span class="italic text-gray-500">null</span>';
                    }
                    if (is_null($newValue)) {
                        $displayNew = '<span class="italic text-gray-500">null</span>';
                    }


                    $changes->push(
                        '<strong>' . e($key) . ':</strong> ' .
                        '<span class="text-red-500 line-through">' . $displayOld . '</span> → ' .
                        '<span class="text-green-500">' . $displayNew . '</span>'
                    );
                }
                continue;
            }
            // JSON文字列ではないが、配列やオブジェクトの場合の表示を調整
            if (is_array($newValue) || is_object($newValue) || is_array($oldValue) || is_object($oldValue)) {
                 // これらの型の場合は、JSON処理のところで 'content_changed' または 'complex_data' として処理されるべき
                 continue;
            }
            // boolean の場合は 'true'/'false' に変換
            $displayNewValue = is_bool($newValue) ? ($newValue ? 'true' : 'false') : (is_null($newValue) ? 'null' : (string)$newValue);
            $displayOldValue = is_bool($oldValue) ? ($oldValue ? 'true' : 'false') : (is_null($oldValue) ? 'null' : (string)$oldValue);

            // 値が変更されている場合のみ追加
            if ($displayNewValue !== $displayOldValue || !$old->has($key)) { // 新規追加の場合も表示
                if ($old->has($key)) { // 既存の属性が変更された場合
                    $changes->push(
                        '<strong>' . $key . ':</strong> ' .
                        '<span class="text-red-500 line-through">' . e($displayOldValue) . '</span> → ' .
                        '<span class="text-green-500">' . e($displayNewValue) . '</span>'
                    );
                } else { // 新規に追加された属性
                    $changes->push(
                        '<strong>' . $key . ':</strong> ' .
                        '<span class="text-green-500">' . e($displayNewValue) . '</span>'
                    );
                }
            }
        }

        // 削除されたプロパティも考慮（old にあって attributes にないもの）
        foreach ($old as $key => $value) {
            if (!$attributes->has($key) && !in_array($key, ['latest_diff_id', 'updated_at', 'modifier_id', 'created_at', 'creator_id', 'deleted_at'])) { // deleted_at は上で処理済み
                $changes->push(
                    '<strong>' . $key . ':</strong> ' .
                    '<span class="text-red-500 line-through">' . e(is_bool($value) ? ($value ? 'true' : 'false') : (is_null($value) ? 'null' : (string)$value)) . '</span> → ' .
                    '<span class="text-gray-500">' . __('ledger.activity.changes.removed') . '</span>'
                );
            }
        }

        if ($changes->isEmpty() && $isUpdated) {
            // 'updated' イベントなのにpropertiesに実質的な変更がない場合
            return __('ledger.activity.changes.no_significant_changes');
        }

        // リレーションイベントの場合の特別なメッセージ
        if ($activity->event === 'attached') {
            $changes->push('<span class="text-green-500">' . __('ledger.activity.changes.attached') . '</span>');
        } elseif ($activity->event === 'detached') {
            $changes->push('<span class="text-red-500">' . __('ledger.activity.changes.detached') . '</span>');
        }


        return new \Illuminate\Support\HtmlString($changes->implode('<br>'));
    }


    /**
     * コメントを表示するためのフォーマット
     *
     * @param CustomActivity $activity
     * @return string
     */
    public function formatComment(CustomActivity $activity): string
    {
        return $activity->properties->get('comments', '');
    }

    /**
     * 対象リソースのタイトルとタイプを取得
     *
     * @param CustomActivity $activity
     * @return string
     */
    public function getSubjectDisplay(CustomActivity $activity): string
    {
        if (!$activity->subject) {
            // subject が null で causer が User の場合 (例: login/logout) は causer を subject のように扱う
            if ($activity->causer instanceof User) {
                return __('ledger.activity.model_name.user') . ': ' . ($activity->causer->name ?? $activity->causer->id);
            }
            return __('ledger.activity.subject.unknown');
        }

        $title = '';
        $type = '';

        if ($activity->subject instanceof Ledger) {
            $title = $activity->subject->define->title ?? ('ID: ' . $activity->subject->id);
            $type = __('ledger.activity.model_name.ledger');
        } elseif ($activity->subject instanceof LedgerDefine) {
            $title = $activity->subject->title ?? ('ID: ' . $activity->subject->id);
            $type = __('ledger.activity.model_name.ledger_define');
        } elseif ($activity->subject instanceof Folder) {
            $title = $activity->subject->title ?? ('ID: ' . $activity->subject->id);
            $type = __('ledger.activity.model_name.folder');
        } elseif ($activity->subject instanceof User) {
            $title = $activity->subject->name ?? ('ID: ' . $activity->subject->id);
            $type = __('ledger.activity.model_name.user');
        } elseif ($activity->subject instanceof Role) {
            $title = $activity->subject->name ?? ('ID: ' . $activity->subject->id);
            $type = __('ledger.activity.model_name.role');
        } elseif ($activity->subject instanceof Organization) {
            $title = $activity->subject->name ?? ('ID: ' . $activity->subject->id);
            $type = __('ledger.activity.model_name.organization');
        } elseif ($activity->subject instanceof Permission) {
            $title = $activity->subject->name ?? ('ID: ' . $activity->subject->id);
            $type = __('ledger.activity.model_name.permission');
        } elseif ($activity->subject instanceof RoleFolderPermission) {
            // RoleFolderPermission は固有の表示
            $roleName = $activity->subject->role->name ?? __('ledger.activity.subject.unknown_role');
            $folderName = $activity->subject->folder->title ?? __('ledger.activity.subject.unknown_folder');
            $type = __('ledger.activity.model_name.role_folder_permission');
            $permissionType = $activity->subject->permission->label() ?? '';
            $notificationTypeName = $activity->subject->notificationType->name ?? '';

            if ($permissionType) {
                $title = "{$roleName} / {$folderName} ({$permissionType})";
            } elseif ($notificationTypeName) {
                $title = "{$roleName} / {$folderName} (通知: {$notificationTypeName})";
            } else {
                $title = "{$roleName} / {$folderName}";
            }
        } else {
            // 未知のモデルタイプの場合、クラス名を表示
            $type = class_basename($activity->subject_type);
            $title = $activity->subject_id;
        }

        return "{$type}: {$title}";
    }

    /**
     * causer の表示名を取得
     *
     * @param CustomActivity $activity
     * @return string
     */
    public function getCauserDisplayName(CustomActivity $activity): string
    {
        if ($activity->causer) {
            return $activity->causer->name;
        }

        // causer が null で、subject_type が App\Models\User 以外の場合（システムユーザー）
        // subject_type が User で causer が null の場合は getSubjectDisplay で処理される
        if ($activity->causer_type !== User::class) { // causer_type が null の場合も含む
            return __('ledger.activity.system_user');
        }

        return __('ledger.activity.unknown_user');
    }


    /**
     * 対象リソースの詳細画面へのリンクURLを取得
     *
     * @param CustomActivity $activity
     * @return string|null
     */
    public function getSubjectDetailLink(CustomActivity $activity): ?string
    {
        if (!$activity->subject) {
            // subject が null で causer が User の場合 (ログイン/ログアウト) は causer のリンクを返す
            if ($activity->causer instanceof User) {
                // return route('filament.admin.resources.users.view', $activity->causer);
                return null; // 一般ユーザー向け画面ではFilamentリンクは表示しない
            }
            return null;
        }

        if ($activity->subject instanceof Ledger) {
            return route('ledger.show', $activity->subject);
        }

        if ($activity->subject instanceof LedgerDefine) {
            // return route('filament.admin.resources.ledger-defines.view', $activity->subject);
            return null;
        }

        if ($activity->subject instanceof Folder) {
            // return route('filament.admin.resources.folders.view', $activity->subject);
            // フォルダは一般ユーザー向けに台帳一覧画面へのリンクを提供する
            return route('ledgersByFolderId', $activity->subject);
        }

        if ($activity->subject instanceof User) {
            // return route('filament.admin.resources.users.view', $activity->subject);
            return null;
        }

        if ($activity->subject instanceof Role) {
            // return route('filament.admin.resources.roles.view', $activity->subject);
            return null;
        }

        if ($activity->subject instanceof Organization) {
            // return route('filament.admin.resources.organizations.view', $activity->subject);
            return null;
        }

        if ($activity->subject instanceof Permission) {
            // return route('filament.admin.resources.permissions.view', $activity->subject);
            return null;
        }

        if ($activity->subject instanceof RoleFolderPermission) {
            // ロールフォルダ権限は Filament の RoleResource から設定されるため、Roleの編集画面へのリンクが適切か
            // return route('filament.admin.resources.roles.edit', $activity->subject->role);
            return null;
        }
        return null;
    }

    /**
     * 操作者の詳細画面へのリンクURLを取得 (現状は使わないが定義しておく)
     *
     * @param CustomActivity $activity
     * @return string|null
     */
    public function getCauserDetailLink(CustomActivity $activity): ?string
    {
        // 管理者向けリンクは表示しない
        return null;
    }

    // `NotificationList` から利用できるようにするためのヘルパー
    // `NotificationList` の `formatNotificationData` から `ActivityHistoryDisplay` のインスタンスを生成して
    // `formatChanges` を呼び出すような使い方を想定
    // ただし、これだと Livewire Component のライフサイクルを無視したインスタンス生成になるため、
    // 静的ヘルパーに切り出す方がより安全。
    // 今回は `ActivityHistoryDisplay` が汎用コンポーネントとして動作するため、
    // `NotificationList` 側を修正して、通知データに含める`changes`のフォーマットを
    // `ActivityHistoryDisplay` のフォーマット結果に合わせるか、
    // 別途ActivityLogFormatterサービスを作るかを検討する。
    // 一旦、この ActivityHistoryDisplay のメソッドはそのまま保持。
}