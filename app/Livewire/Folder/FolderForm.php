<?php

namespace App\Livewire\Folder;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Models\Role;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Mary\Traits\Toast;

class FolderForm extends BaseLivewireComponent
{
    use InitializesTenantContext, Toast;

    #[Locked]
    public ?Folder $folder = null; // Livewireがルートモデルバインディング (編集時) または new Folder() (作成時) をセット

    public ?int $folderId = null;

    public ?int $parentId = null; // Livewireがルートパラメータ {parentId?} (作成時) をセット

    public string $title = '';

    public array $selectedInspectorRoleIds = [];

    public array $selectedApproverRoleIds = [];

    public SupportCollection $availableParentFolders;

    public SupportCollection $availableRoles;

    public bool $isCreating = false;

    public string $confidentialityLevel = '';

    public array $confidentialityScopes = [];

    // --- 削除確認モーダル用 ---
    public bool $confirmingFolderDeletion = false;

    // --- 状態管理用フラグ ---
    public bool $formDisabled = false; // 削除後にフォームを無効化するため

    public bool $justSaved = false; // 保存直後かどうかのフラグ

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'parentId' => [
                Rule::requiredIf(fn () => $this->isCreating && Folder::count() > 0), // 最初のフォルダ作成時は親不要
                'nullable',
                'integer',
                Rule::exists('folders', 'id')->whereNot('id', $this->folderId ?? 0),
            ],
            'selectedInspectorRoleIds' => ['array'],
            'selectedInspectorRoleIds.*' => ['integer', 'exists:roles,id'],
            'selectedApproverRoleIds' => ['array'],
            'selectedApproverRoleIds.*' => ['integer', 'exists:roles,id'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'title' => __('ledger.folder.form.label.title'),
            'parentId' => __('ledger.folder.form.label.parent_id'),
            'selectedInspectorRoleIds' => __('ledger.folder.form.label.required_inspector_roles'),
            'selectedApproverRoleIds' => __('ledger.folder.form.label.required_approver_roles'),
        ];
    }

    // mountメソッドの引数を削除
    public function mount(): void
    {
        if ($this->folderId) {
            $this->folder = Folder::find($this->folderId);
        }

        // Livewireによるプロパティバインディングの後に、folderプロパティがセットされていることを確認
        // ただし、新規作成時はLivewireがnew Folder()をセットするため、existsはfalse
        if (! isset($this->folder) || ! $this->folder instanceof Folder) {
            $this->folder = new Folder;
        }

        // isCreating フラグを先に設定
        $this->isCreating = ! $this->folder->exists;

        // 認可チェック
        if ($this->isCreating) { // 新規作成モード
            $canCreate = auth()->user()->can('create', Folder::class);
            if (! $canCreate) {
                abort(403, __('auth.unauthorized'));
            }
        } else { // 編集モード
            $canUpdate = auth()->user()->can('update', $this->folder);
            if (! $canUpdate) {
                abort(403, __('auth.unauthorized'));
            }
            $this->tenantId = $this->folder->tenant_id;
        }

        $this->availableParentFolders = collect();
        $this->availableRoles = collect();

        if ($this->folder->exists) {
            $this->folderId = $this->folder->id; // ★ 追加
            // 編集モードの初期化
            $this->title = $this->folder->title;
            $this->parentId = $this->folder->parent_id;
            $this->selectedInspectorRoleIds = $this->folder->requiredInspectorRoles()->pluck('roles.id')->toArray();
            $this->selectedApproverRoleIds = $this->folder->requiredApproverRoles()->pluck('roles.id')->toArray();
        } else {
            // 新規作成モードの初期化
            if (is_null($this->parentId) && Folder::count() > 0) {
                $this->parentId = Folder::whereIsRoot()->first()?->id;
            }
            $this->title = '';
            $this->selectedInspectorRoleIds = [];
            $this->selectedApproverRoleIds = [];
        }

        $this->loadAvailableParents();
        $this->loadAvailableRoles();
        //        $this->tenantId = tenant()?->id;
    }

    protected function loadAvailableParents(): void
    {
        $allFolders = Folder::orderBy('_lft')->get();
        $tree = $allFolders->toTree(); // ルートノードのコレクションを取得
        $options = [];

        // 最初の選択肢として「ルートフォルダ（親なし）」を追加 (新規作成時または親がいない場合)
        if ($this->isCreating || ! $this->parentId) {
            $options[] = ['id' => null, 'name' => __('ledger.folder.form.option.no_parent')];
        }

        $traverse = function ($nodes, $prefix = '') use (&$traverse, &$options) {
            foreach ($nodes as $node) {
                // 編集時、自分自身とその子孫は親として選択肢に含めない
                if (! $this->isCreating && $this->folder->exists && ($node->id === $this->folder->id || $node->isDescendantOf($this->folder))) {
                    continue;
                }
                $options[] = ['id' => $node->id, 'name' => $prefix.' '.$node->title];
                if ($node->children->isNotEmpty()) {
                    $traverse($node->children, $prefix.str_repeat(' ', 2).'-'); // インデントを調整
                }
            }
        };
        $traverse($tree);
        $this->availableParentFolders = collect($options);
    }

    // ロール選択肢をロードするメソッド
    protected function loadAvailableRoles(): void
    {
        $this->availableRoles = Role::orderBy('name')
            ->get(); /*            ->map(function ($role) {
                // MaryUI Select 用の形式: ['id' => ..., 'name' => ...]
                return ['id' => $role->id, 'name' => $role->name]; // Roleモデルのnameをそのまま使用
            })*/

    }

    public function save(): void
    {
        $this->validate();

        DB::beginTransaction();
        try {
            // 1. フォルダの基本情報を設定
            if (! $this->isCreating) {
                // 更新時は、Livewireのプロパティのデシリアライズ問題を避けるため、
                // DBから最新のモデルを取得し直してからプロパティをセットする
                $this->folder = Folder::find($this->folderId);
            }
            $this->folder->title = $this->title;
            $this->folder->modifier_id = Auth::id();
            $isNewRecordBeforeSave = $this->isCreating; // 保存前の状態を保持
            if ($this->isCreating) {
                $this->folder->creator_id = Auth::id();
                // tenant() が null でないことを確認してから tenant_id を設定
                //                if (tenancy()->tenant) { // Stancl\Tenancy のヘルパー関数 tenancy() を使用
                if ($this->tenantId) {
                    //                    $this->folder->tenant_id = tenancy()->tenant->id;
                    $this->folder->tenant_id = $this->tenantId;
                } else {
                    // テナントコンテキストがない場合の処理 (エラーログ、またはデフォルト値の設定など)
                    Log::warning('Attempted to create folder without tenant context.');
                    // 必要に応じてエラーをスローするか、処理を中断する
                    $this->error(__('messages.error.no_tenant_context'));

                    return;
                }
            }
            //            dd($this->folder);

            // 2. フォルダの保存 (NestedSetの操作)
            if ($this->isCreating) {
                if ($this->parentId) {
                    $parent = Folder::find($this->parentId);
                    if ($parent) {
                        // $this->folder->appendToNode($parent)->save(); // これでも良い
                        $this->folder->parent_id = $parent->id;
                        $this->folder->save(); // parent_id をセットして save
                    } else {
                        if (Folder::count() === 0) {
                            $this->folder->saveAsRoot();
                        } else {
                            DB::rollBack();
                            $this->error(__('messages.error.parent_folder_not_found'));

                            return;
                        }
                    }
                } else {
                    if (Folder::count() > 0) {
                        DB::rollBack();
                        $this->addError('parentId', __('validation.required', ['attribute' => __('ledger.folder.form.label.parent_id')]));

                        return;
                    }
                    $this->folder->saveAsRoot();
                }
            } else { // 更新の場合
                // タイトルなどの基本情報をまず保存
                $this->folder->save();

                // 親が変更されたか確認し、変更されていれば移動
                if ($this->folder->getOriginal('parent_id') != $this->parentId) {
                    if ($this->parentId) {
                        $newParent = Folder::find($this->parentId);
                        if ($newParent && ! $newParent->isDescendantOf($this->folder) && $newParent->id !== $this->folder->id) {
                            // $this->folder->appendToNode($newParent)->save(); // これでも良い
                            $this->folder->parent_id = $newParent->id;
                            $this->folder->save(); // parent_id を変更して save
                        } else {
                            DB::rollBack();
                            $this->error(__('messages.error.invalid_parent_folder_selection'));

                            return;
                        }
                    } else { // 親が null に設定された = ルートに移動
                        if (! $this->folder->isRoot()) {
                            $this->folder->saveAsRoot();
                        }
                    }
                }
            }

            // 3. 必須ロールの同期処理 (フォルダの保存が成功した後)
            // 既存の関連を一度クリアしてから新しい関連を attach する
            $this->folder->requiredInspectorRoles()->wherePivot('type', 'inspector')->detach();
            if (! empty($this->selectedInspectorRoleIds)) {
                $inspectorAttachData = [];
                foreach ($this->selectedInspectorRoleIds as $roleId) {
                    $inspectorAttachData[$roleId] = ['type' => 'inspector'];
                }
                $this->folder->requiredInspectorRoles()->attach($inspectorAttachData);
            }

            $this->folder->requiredApproverRoles()->wherePivot('type', 'approver')->detach();
            if (! empty($this->selectedApproverRoleIds)) {
                $approverAttachData = [];
                foreach ($this->selectedApproverRoleIds as $roleId) {
                    $approverAttachData[$roleId] = ['type' => 'approver'];
                }
                $this->folder->requiredApproverRoles()->attach($approverAttachData);
            }

            DB::commit();

            $this->success($this->isCreating ?
                __('ledger.folder.form.message.created_successfully') :
                __('ledger.folder.form.message.updated_successfully'));

            if ($isNewRecordBeforeSave) {
                // ★ 新規作成後は、フォームをリセットして「続けて新規作成」できる状態にするか、
                //    編集モードに移行して現在のフォルダを表示し続けるか選択できる。
                //    ここでは編集モードに移行する。
                $this->isCreating = false;
                // $this->folder は保存されたインスタンスになっている
                //                 $this->mount(); // 再マウントしてフォーム値を更新
            } else {
                // 更新後は現在の編集状態を維持
                $this->folder->refresh();
                //                 $this->mount();
            }
            $this->justSaved = true; // 保存直後フラグを立てる
            $this->dispatch('folderSavedAndRefreshList', folderId: $this->folder->id); // 親ウィンドウのリスト更新用イベント

        } catch (\Exception $e) {
            // DB::rollBack();
            Log::error('Folder save failed: '.$e->getMessage(), [
                'folder_title' => $this->title,
                'parent_id' => $this->parentId,
                'exception' => $e,
            ]);
            $this->error(__('messages.error.save_failed'), $e->getMessage());
        }
    }

    /**
     * 削除確認モーダルを表示する
     */
    public function confirmFolderDeletion(): void
    {
        // 削除権限チェック (任意)
        // if (auth()->user()->cannot('delete', $this->folder)) {
        //     $this->error(__('削除権限がありません。'));
        //     return;
        // }
        if ($this->isCreating || ! $this->folder->exists) {
            return;
        }

        if ($this->folder->children()->count() > 0) {
            $this->warning(__('ledger.folder.form.message.delete_has_children'));

            return;
        }
        if ($this->folder->ledgerDefines()->count() > 0) {
            $this->warning(__('ledger.folder.form.message.delete_has_defines'));

            return;
        }
        $this->confirmingFolderDeletion = true;
    }

    /**
     * フォルダを削除する
     */
    public function deleteFolder(): void
    {
        if ($this->isCreating || ! $this->folder->exists) {
            return;
        }

        if ($this->folder->children()->count() > 0 || $this->folder->ledgerDefines()->count() > 0) {
            $this->warning(__('ledger.folder.form.warning.cannot_delete_if_children_exist'));
            $this->confirmingFolderDeletion = false;

            return;
        }

        try {
            DB::beginTransaction();
            // 関連する必須ロール設定も削除 (belongsToMany の sync([]) でも可)
            $this->folder->requiredInspectorRoles()->detach();
            $this->folder->requiredApproverRoles()->detach();
            $this->folder->delete(); // NestedSet が適切に処理
            DB::commit();

            $this->success(__('ledger.folder.form.message.deleted_successfully'));
            $this->dispatch('folderDeleted'); // イベント発行
            $this->formDisabled = true; // ★ フォームを無効化
            $this->dispatch('folderSavedAndRefreshList'); // 親ウィンドウのリスト更新用イベント

            // 削除後は一覧に戻るなどの処理
            // return redirect()->route('folders.index'); // 仮のルート

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Folder deletion failed: '.$e->getMessage(), ['folder_id' => $this->folder->id]);
            $this->error(__('messages.error.delete_failed'), $e->getMessage());
        } finally {
            $this->confirmingFolderDeletion = false;
        }
    }

    /**
     * フォームを初期状態にリセットする (続けて新規作成用)
     */
    public function resetFormForNew(): void
    {
        $this->folder = new Folder;
        $this->title = '';
        // 親IDは最後に選択したものを維持するか、クリアするか、URLから再取得するか
        // ここでは最後に選択したものを維持する例
        // $this->parentId = null; // または $this->parentId = request()->input('parent_id', Folder::whereIsRoot()->first()?->id);
        $this->selectedInspectorRoleIds = [];
        $this->selectedApproverRoleIds = [];
        $this->isCreating = true;
        $this->formDisabled = false;
        $this->justSaved = false;
        $this->resetValidation();
        $this->loadAvailableParents(); // 親フォルダリストも再読み込み
    }

    public function render()
    {
        $confidentialityLevelOptions = [
            ['id' => 'public', 'name' => __('ledger.confidentiality.level.public')],
            ['id' => 'internal', 'name' => __('ledger.confidentiality.level.internal')],
            ['id' => 'confidential', 'name' => __('ledger.confidentiality.level.confidential')],
            ['id' => 'secret', 'name' => __('ledger.confidentiality.level.secret')],
        ];

        $confidentialityScopeOptions = [
            ['id' => 'org_1', 'name' => '人事部'],
            ['id' => 'org_2', 'name' => '経理部'],
            ['id' => 'role_1', 'name' => '管理者'],
            ['id' => 'role_2', 'name' => '一般ユーザー'],
        ];

        return view('livewire.folder.folder-form', [
            'confidentialityLevelOptions' => $confidentialityLevelOptions,
            'confidentialityScopeOptions' => $confidentialityScopeOptions,
        ])->layout('layouts.app', ['title' => __('ledger.folder.settings')]);
    }
}
