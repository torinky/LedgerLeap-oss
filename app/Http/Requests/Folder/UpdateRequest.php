<?php
use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $folderId = $this->route('folder'); // ルートパラメータからfolder IDを取得
        $folder = Folder::findOrFail($folderId); // Folderモデルを取得
        return true;
    }
}
