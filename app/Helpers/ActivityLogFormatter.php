<?php

namespace App\Helpers;

use App\Models\AttachedFile;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

/**
 * ActivityLogのデータを整形するためのヘルパークラス
 */
class ActivityLogFormatter
{
    // DaisyUI Theme Colors for Changes Display - ActivityHistoryDisplay と共通化
    private const TEXT_COLOR_SUCCESS = 'text-success';

    private const TEXT_COLOR_ERROR = 'text-error';

    private const TEXT_COLOR_INFO = 'text-info';

    private const TEXT_STYLE_MUTED = 'text-base-content/70';

    private const TEXT_STYLE_ITALIC_MUTED = 'italic '.self::TEXT_STYLE_MUTED;

    /**
     * アクティビティの操作内容をユーザーフレンドリーな文字列に変換
     *
     * @param  CustomActivity|array  $activity  Activityモデルインスタンスまたはペイロード配列
     */
    public static function getOperationDescription(Activity|array $activity): string
    {
        // Activityモデルインスタンスの場合
        if ($activity instanceof Activity) {
            $eventKey = $activity->event ?? $activity->description;
            $subjectType = $activity->subject_type;
            $properties = $activity->properties;
        } else { // array (通知ペイロードなど) の場合
            $eventKey = $activity['event'] ?? ($activity['notification_type_name'] ?? $activity['description'] ?? null);
            $subjectType = $activity['subject_type'] ?? null;
            $properties = collect($activity['properties'] ?? []);
        }

        $subjectName = self::getSubjectNameForDisplay($activity);

        // subjectTypeBase の取得を試みる (class_basename を使う)
        $subjectTypeBase = $subjectType ? strtolower(class_basename($subjectType)) : null;

        // 特定のイベントタイプを優先して翻訳キーを決定
        if (in_array($eventKey, ['created', 'updated', 'deleted', 'restored', 'forceDeleted'])) {
            $key = "ledger.activity.event.{$subjectTypeBase}.{$eventKey}";
            if (__($key) !== $key) { // 翻訳キーが存在するか確認
                return __($key, ['resource' => $subjectName]);
            }

            // fallback for generic model events
            return __("ledger.activity.event.{$eventKey}", ['resource' => $subjectName]);
        }
        // ワークフロー関連のカスタムイベント
        if (in_array(
            $eventKey,
            [
                'requested_inspection',
                'inspection_completed',
                'approved', 'returned_to_draft', 'edited_while_pending', 'task_claimed',
            ]
        )) {
            return __("ledger.activity.workflow.{$eventKey}", ['resource' => $subjectName]);
        }
        // ログイン・ログアウト
        if (in_array($eventKey, ['login', 'logout'])) {
            return __("ledger.activity.event.{$eventKey}", ['resource' => $subjectName]);
        }
        // File operation events
        if (in_array($eventKey, ['downloaded', 'downloaded_original', 'viewed_thumbnail', 'downloaded_vlm'])) {
            return __("ledger.activity.event.{$eventKey}", ['resource' => $subjectName]);
        }
        // リレーション操作 (attached/detached)
        if (in_array($eventKey, ['attached', 'detached'])) {
            $relationName = $properties->get('relation_name');
            $relationEventKey = strtolower(class_basename($subjectType)).'_'.$relationName.'_'.$eventKey;
            $key = "ledger.activity.event.{$relationEventKey}";
            if (__($key) !== $key) {
                return __($key, ['resource' => $subjectName, 'related_entity' => self::getRelatedEntityNameForDisplay($activity)]);
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
        return is_string($eventKey) ? $eventKey : ''; // fallback to raw event name or empty string
    }

    /**
     * subject の表示名を取得するヘルパー
     */
    public static function getSubjectNameForDisplay(Activity|array $activity): string
    {
        $subject = ($activity instanceof Activity) ? $activity->subject : null;
        $subjectType = ($activity instanceof Activity) ? $activity->subject_type : ($activity['subject_type'] ?? null);
        $subjectId = ($activity instanceof Activity) ? $activity->subject_id : ($activity['subject_id'] ?? null);

        // subject が null で causer が User の場合 (例: login/logout) は causer を subject のように扱う
        $causer = ($activity instanceof Activity) ? $activity->causer : null;
        if (! $subject) {
            if ($causer instanceof User) {
                return $causer->name ?? __('ledger.activity.subject.unknown');
            }

            return __('ledger.activity.subject.unknown');
        }

        $title = '';
        $type = '';

        // モデルインスタンスがある場合
        if ($subject instanceof Ledger) {
            $title = $subject->define->title ?? ('ID: '.$subject->id);
            $type = __('ledger.activity.model_name.ledger');
        } elseif ($subject instanceof LedgerDefine) {
            $title = $subject->title ?? ('ID: '.$subject->id);
            $type = __('ledger.activity.model_name.ledger_define');
        } elseif ($subject instanceof Folder) {
            $title = $subject->title ?? ('ID: '.$subject->id);
            $type = __('ledger.activity.model_name.folder');
        } elseif ($subject instanceof User) {
            $title = $subject->name ?? ('ID: '.$subject->id);
            $type = __('ledger.activity.model_name.user');
        } elseif ($subject instanceof Role) {
            $title = $subject->name ?? ('ID: '.$subject->id);
            $type = __('ledger.activity.model_name.role');
        } elseif ($subject instanceof Organization) {
            $title = $subject->name ?? ('ID: '.$subject->id);
            $type = __('ledger.activity.model_name.organization');
        } elseif ($subject instanceof Permission) {
            $title = $subject->name ?? ('ID: '.$subject->id);
            $type = __('ledger.activity.model_name.permission');
        } elseif ($subject instanceof RoleFolderPermission) {
            $roleName = $subject->role->name ?? __('ledger.activity.subject.unknown_role');
            $folderName = $subject->folder->title ?? __('ledger.activity.subject.unknown_folder');
            $type = __('ledger.activity.model_name.role_folder_permission');
            $permissionType = $subject->permission->label() ?? '';
            $notificationTypeName = $subject->notificationType->name ?? '';

            if ($permissionType) {
                $title = "{$roleName} / {$folderName} ({$permissionType})";
            } elseif ($notificationTypeName) {
                $title = "{$roleName} / {$folderName} (通知: {$notificationTypeName})";
            } else {
                $title = "{$roleName} / {$folderName}";
            }
        } elseif ($subject instanceof AttachedFile) {
            $title = $subject->original_filename ?? $subject->filename ?? ('ID: '.$subject->id);
            $type = __('ledger.activity.model_name.attached_file');
        } else {
            // subject モデルが取得できなかった場合や不明なモデルタイプ
            $type = class_basename($subjectType);
            $title = 'ID: '.$subjectId;
        }

        return "{$type}: [ {$title} ] ";
    }

    /**
     * リレーション操作時の関連エンティティ名を取得
     */
    public static function getRelatedEntityNameForDisplay(Activity|array $activity): string
    {
        $properties = ($activity instanceof Activity) ? $activity->properties : collect($activity['properties'] ?? []);

        $relatedModelType = $properties->get('related_model_type');
        $relatedModelIds = $properties->get('related_model_ids', []);

        if (empty($relatedModelIds)) {
            return '';
        }

        $relatedEntities = collect();
        if ($relatedModelType && class_exists($relatedModelType)) {
            try {
                $relatedEntities = $relatedModelType::whereIn('id', $relatedModelIds)->get();
            } catch (\Throwable $e) {
                // モデルやリレーションが見つからない等のエラーは無視
            }
        }

        if ($relatedEntities->isNotEmpty()) {
            return $relatedEntities->map(function ($entity) {
                return $entity->name ?? $entity->id;
            })->implode(', ');
        }

        $relationName = $properties->get('relation_name');
        if ($relationName) {
            return __('ledger.activity.changes.related_entity_of', ['relation' => $relationName]);
        }

        return '';
    }

    /**
     * ActivityLogの変更差分をHTML形式でフォーマットする
     *
     * @param  CustomActivity|array  $activity  Activityモデルインスタンスまたはペイロード配列
     */
    public static function formatChanges(Activity|array $activity): string|HtmlString
    {
        // Activityモデルインスタンスの場合
        if ($activity instanceof Activity) {
            $properties = $activity->properties;
            $event = $activity->event;
        } else { // array (通知ペイロードなど) の場合
            $properties = collect($activity['properties'] ?? []);
            $event = $activity['event'] ?? null;
        }

        if (! $properties->has('attributes') && ! $properties->has('old')) {
            return '';
        }

        $attributes = collect($properties->get('attributes', []));
        $old = collect($properties->get('old', []));

        $changes = collect();
        $isUpdated = $event === 'updated';

        foreach ($attributes as $key => $newValue) {
            $oldValue = $old->get($key);

            // `latest_diff_id`, `updated_at`, `modifier_id`, `created_at`, `creator_id` はスキップ
            if (in_array($key, ['latest_diff_id', 'updated_at', 'modifier_id', 'created_at', 'creator_id'])) {
                continue;
            }
            // `deleted_at` は論理削除で自動的に設定されるため、表示を簡略化
            if ($key === 'deleted_at') {
                if ($newValue !== null && $oldValue === null) {
                    $changes->push(
                        '<strong>'.e($key).':</strong> '.
                        '<span class="'.self::TEXT_COLOR_SUCCESS.'">'.__('ledger.activity.changes.attached').'</span>'
                    );
                } elseif ($newValue === null && $oldValue !== null) {
                    $changes->push(
                        '<strong>'.e($key).':</strong> '.
                        '<span class="'.self::TEXT_COLOR_ERROR.' line-through">'.__('ledger.activity.changes.detached').'</span>'
                    );
                }

                continue;
            }

            // パスワード変更
            if ($key === 'password') {
                $changes->push(
                    '<span class="'.self::TEXT_STYLE_MUTED.'">'.__('ledger.activity.changes.password_changed').'</span>'
                );

                continue;
            }

            // JSON文字列のカラム (content, column_define, completed_inspector_role_ids, completed_approver_role_ids, folder_required_roles)
            if (in_array($key, ['content', 'column_define', 'completed_inspector_role_ids', 'completed_approver_role_ids', 'folder_required_roles'])) {
                $isNewValueJsonString = is_string($newValue);
                $isOldValueJsonString = is_string($oldValue);

                $decodedNew = $isNewValueJsonString ? json_decode($newValue, true) : $newValue;
                $newJsonError = $isNewValueJsonString ? json_last_error() : JSON_ERROR_NONE;

                $decodedOld = $isOldValueJsonString ? json_decode($oldValue, true) : $oldValue;
                $oldJsonError = $isOldValueJsonString ? json_last_error() : JSON_ERROR_NONE;

                if (
                    (($isNewValueJsonString && $newJsonError === JSON_ERROR_NONE) || ! $isNewValueJsonString) &&
                    (($isOldValueJsonString && $oldJsonError === JSON_ERROR_NONE) || ! $isOldValueJsonString)
                ) {
                    if ($decodedNew !== $decodedOld) {
                        $changes->push('<strong>'.e($key).':</strong> <span class="'.self::TEXT_COLOR_INFO.'">'.__('ledger.activity.changes.content_changed').'</span>');
                    }
                } elseif ((is_string($newValue) || is_null($newValue) || is_bool($newValue)) && (is_string($oldValue) || is_null($oldValue) || is_bool($oldValue)) && (string) $newValue !== (string) $oldValue) {
                    $displayOld = is_array($oldValue) || is_object($oldValue) ? __('ledger.activity.changes.complex_data') : e((string) $oldValue);
                    $displayNew = is_array($newValue) || is_object($newValue) ? __('ledger.activity.changes.complex_data') : e((string) $newValue);

                    if (is_null($oldValue)) {
                        $displayOld = '<span class="'.self::TEXT_STYLE_ITALIC_MUTED.'">null</span>';
                    }
                    if (is_null($newValue)) {
                        $displayNew = '<span class="'.self::TEXT_STYLE_ITALIC_MUTED.'">null</span>';
                    }

                    $changes->push(
                        '<strong>'.e($key).':</strong> '.
                        '<span class="'.self::TEXT_COLOR_ERROR.' line-through">'.$displayOld.'</span> → '.
                        '<span class="'.self::TEXT_COLOR_SUCCESS.'">'.$displayNew.'</span>'
                    );
                }

                continue;
            }
            // 配列やオブジェクトだがJSON処理の対象ではないカラム
            if (is_array($newValue) || is_object($newValue) || is_array($oldValue) || is_object($oldValue)) {
                if ($newValue !== $oldValue) {
                    $changes->push('<strong>'.e($key).':</strong> <span class="'.self::TEXT_COLOR_INFO.'">'.__('ledger.activity.changes.complex_data').'</span>');
                }

                continue;
            }

            // boolean の場合は 'true'/'false' に変換
            $displayNewValue = is_bool($newValue) ? ($newValue ? 'true' : 'false') : (is_null($newValue) ? 'null' : (string) $newValue);
            $displayOldValue = is_bool($oldValue) ? ($oldValue ? 'true' : 'false') : (is_null($oldValue) ? 'null' : (string) $oldValue);

            // 値が変更されている場合のみ追加
            if ($displayNewValue !== $displayOldValue || ! $old->has($key)) {
                if ($old->has($key)) {
                    $changes->push(
                        '<strong>'.e($key).':</strong> '.
                        '<span class="'.self::TEXT_COLOR_ERROR.' line-through">'.e($displayOldValue).'</span> → '.
                        '<span class="'.self::TEXT_COLOR_SUCCESS.'">'.e($displayNewValue).'</span>'
                    );
                } else {
                    $changes->push(
                        '<strong>'.e($key).':</strong> '.
                        '<span class="'.self::TEXT_COLOR_SUCCESS.'">'.e($displayNewValue).'</span>'
                    );
                }
            }
        }

        // 削除されたプロパティも考慮（old にあって attributes にないもの）
        foreach ($old as $key => $value) {
            if (! $attributes->has($key) && ! in_array($key, ['latest_diff_id', 'updated_at', 'modifier_id', 'created_at', 'creator_id', 'deleted_at'])) {
                $changes->push(
                    '<strong>'.e($key).':</strong> '.
                    '<span class="'.self::TEXT_COLOR_ERROR.' line-through">'.e(is_bool($value) ? ($value ? 'true' : 'false') : (is_null($value) ? 'null' : (string) $value)).'</span> → '.
                    '<span class="'.self::TEXT_STYLE_MUTED.'">'.__('ledger.activity.changes.removed').'</span>'
                );
            }
        }

        if ($changes->isEmpty() && $isUpdated) {
            return __('ledger.activity.changes.no_significant_changes');
        }

        // リレーションイベントの場合の特別なメッセージ
        if ($event === 'attached') {
            $changes->push('<span class="'.self::TEXT_COLOR_SUCCESS.'">'.__('ledger.activity.changes.attached').'</span>');
        } elseif ($event === 'detached') {
            $changes->push('<span class="'.self::TEXT_COLOR_ERROR.'">'.__('ledger.activity.changes.detached').'</span>');
        }

        return new HtmlString($changes->implode('<br>'));
    }

    /**
     * コメントを表示するためのフォーマット
     */
    public static function formatComment(Activity|array $activity): string
    {
        $properties = ($activity instanceof Activity) ? $activity->properties : collect($activity['properties'] ?? []);

        return $properties->get('comments', '');
    }

    /**
     * 対象リソースの詳細画面へのリンクURLを取得
     */
    public static function getSubjectDetailLink(Activity|array $activity): ?string
    {
        $subject = ($activity instanceof Activity) ? $activity->subject : null;

        // Add logging to inspect the subject
        /*        if ($subject) {
                    \Illuminate\Support\Facades\Log::info('getSubjectDetailLink called.', [
                        'subject_type' => get_class($subject),
                        'subject_id' => $subject->id ?? 'null',
                        'subject_attributes' => $subject->getAttributes() ?? [],
                    ]);
                }*/
        //        $subject = ($activity instanceof CustomActivity) ? $activity->subject : null;
        $subjectType = ($activity instanceof CustomActivity) ? $activity->subject_type : ($activity['subject_type'] ?? null);
        $subjectId = ($activity instanceof CustomActivity) ? $activity->subject_id : ($activity['subject_id'] ?? null);

        if (! $subject && $subjectType && $subjectId) {
            // 通知ペイロードなどでモデルインスタンスがロードされていない場合、動的にロードを試みる
            try {
                $subject = $subjectType::find($subjectId);
            } catch (\Throwable $e) {
                $subject = null;
            }
        }

        if (! $subject) {
            // subject が null で causer が User の場合 (ログイン/ログアウト) は causer のリンクを返す
            $causer = ($activity instanceof Activity) ? $activity->causer : null;
            if ($causer instanceof User) {
                // return route('filament.admin.resources.users.view', $causer);
                return null; // 一般ユーザー向け画面ではFilamentリンクは表示しない
            }

            return null;
        }

        if ($subject instanceof Ledger) {
            return route('ledger.show', $subject);
        }
        if ($subject instanceof LedgerDefine) {
            return route('ledgersByDefineId', $subject);
        }
        if ($subject instanceof Folder) {
            return route('ledgersByFolderId', ['tenant' => tenant()?->id, 'folderId' => $subject->id]);
        }
        // 以下、Filament 管理画面へのリンクは一般ユーザー向けではないため null を返す
        if ($subject instanceof User || $subject instanceof Role || $subject instanceof Organization || $subject instanceof Permission || $subject instanceof RoleFolderPermission) {
            return null;
        }

        return null;
    }

    /**
     * 操作者の表示名を取得
     */
    public static function getCauserDisplayName(Activity|array $activity): string
    {
        $causer = ($activity instanceof Activity) ? $activity->causer : null;
        $causerType = ($activity instanceof Activity) ? $activity->causer_type : ($activity['causer_type'] ?? null);
        $causerId = ($activity instanceof Activity) ? $activity->causer_id : ($activity['causer_id'] ?? null);

        if ($causer) {
            return $causer->name;
        }

        // causer が null で、subject_type が App\Models\User 以外の場合（システムユーザー）
        if ($causerType !== User::class) {
            return __('ledger.activity.system_user');
        }

        return __('ledger.activity.unknown_user');
    }

    /**
     * 対象リソースのタイトルとタイプを取得
     */
    public static function getSubjectDisplay(Activity $activity): string
    {
        if (! $activity->subject) {
            // subject が null で causer が User の場合 (例: login/logout) は causer を subject のように扱う
            if ($activity->causer instanceof User) {
                return __('ledger.activity.model_name.user').': '.($activity->causer->name ?? $activity->causer->id);
            }

            return __('ledger.activity.subject.unknown');
        }

        $title = '';
        $type = '';

        if ($activity->subject instanceof Ledger) {
            $title = $activity->subject->define->title ?? ('ID: '.$activity->subject->id);
            $type = __('ledger.activity.model_name.ledger');
        } elseif ($activity->subject instanceof LedgerDefine) {
            $title = $activity->subject->title ?? ('ID: '.$activity->subject->id);
            $type = __('ledger.activity.model_name.ledger_define');
        } elseif ($activity->subject instanceof Folder) {
            $title = $activity->subject->title ?? ('ID: '.$activity->subject->id);
            $type = __('ledger.activity.model_name.folder');
        } elseif ($activity->subject instanceof User) {
            $title = $activity->subject->name ?? ('ID: '.$activity->subject->id);
            $type = __('ledger.activity.model_name.user');
        } elseif ($activity->subject instanceof Role) {
            $title = $activity->subject->name ?? ('ID: '.$activity->subject->id);
            $type = __('ledger.activity.model_name.role');
        } elseif ($activity->subject instanceof Organization) {
            $title = $activity->subject->name ?? ('ID: '.$activity->subject->id);
            $type = __('ledger.activity.model_name.organization');
        } elseif ($activity->subject instanceof Permission) {
            $title = $activity->subject->name ?? ('ID: '.$activity->subject->id);
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
     * 操作者の詳細画面へのリンクURLを取得 (現状は使わないが定義しておく)
     */
    public static function getCauserDetailLink(Activity $activity): ?string
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
