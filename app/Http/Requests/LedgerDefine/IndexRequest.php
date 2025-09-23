<?php

namespace App\Http\Requests\LedgerDefine;

use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            //
        ];
    }

    public function folderId()
    {
        // リクエストまたはルートにfolderIdがあればそれを使用
        $folderId = $this->input('folderId') ?? $this->route('folderId');

        if ($folderId) {
            return $folderId;
        }

        // なければ、ユーザーがアクセス可能なルートフォルダのIDをデフォルトとする
        $user = auth()->user();
        if ($user) {
            $rootFolderId = app(UserService::class)->getAccessibleRootFolderIdForUser($user);
            if ($rootFolderId) {
                return $rootFolderId;
            }
        }

        // ユーザーがログインしていない、またはアクセス可能なルートフォルダがない場合は、
        // アプリケーションのルートフォルダのID (1) をフォールバックとして使用
        return 1;
    }
}
