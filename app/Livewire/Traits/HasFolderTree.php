<?php

namespace App\Livewire\Traits;

use App\Models\Folder;
use Illuminate\Support\Collection;

trait HasFolderTree
{
    /**
     * @var array|Collection
     */
    public $folderRecords = [];

    /**
     * @var array|Collection
     */
    public $folderIdNameMap = [];

    /**
     * フォルダの階層構造を取得してマッピングを初期化する
     */
    protected function initializeFolderTree(?int $selectedFolderId = null): void
    {
        $this->folderRecords = [];
        $nodes = Folder::get()->toTree();

        $traverse = function ($folders, $prefix = '-') use (&$traverse) {
            foreach ($folders as $folder) {
                $folder->title = $prefix.' '.$folder->title;
                $this->folderRecords[] = $folder;

                $traverse($folder->children, $prefix.'-');
            }
        };

        $traverse($nodes);
        $this->folderRecords = collect($this->folderRecords);

        $this->folderIdNameMap = $this->folderRecords->mapWithKeys(function ($folderRecord) use ($selectedFolderId) {
            $selected = $folderRecord->id == $selectedFolderId;

            return [
                $folderRecord->id => [
                    'id' => $folderRecord->id,
                    'name' => $folderRecord->title,
                    'selected' => $selected,
                ],
            ];
        });
    }
}
