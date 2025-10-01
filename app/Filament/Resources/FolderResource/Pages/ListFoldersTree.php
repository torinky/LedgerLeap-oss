<?php

namespace App\Filament\Resources\FolderResource\Pages;

use App\Filament\Resources\FolderResource;
use App\Models\Folder;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Contracts\Support\Htmlable;
use Kalnoy\Nestedset\QueryBuilder;
use Studio15\FilamentTree\Components\TreePage;

class ListFoldersTree extends TreePage
{
    protected static string $resource = FolderResource::class;

    /**
     * ページのタイトルを日本語化します。
     */
    public function getTitle(): string|Htmlable
    {
        // lang/ja/ledger.php に 'folders' => 'フォルダー' のようなキーを追加してください
        return __('ledger.settings.folder');
    }

    /**
     * ヘッダーに表示するアクションを定義します。
     * TreePageのデフォルトのモーダル作成ボタンをオーバーライドし、
     * 専用の作成ページに遷移させます。
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->url(FolderResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
            Actions\Action::make('list_view')
                ->label(__('ledger.views.list'))
                ->icon('heroicon-o-list-bullet')
                ->color('info')
                ->url(FolderResource::getUrl('index')),
        ];
    }

    /**
     * TreePageで必須のメソッド：使用するモデルを返します。
     */
    public static function getModel(): string|QueryBuilder
    {
        return Folder::class;
    }

    /**
     * ツリー上で新しいフォルダを作成する際のモーダルフォームを定義します。
     * シンプルにフォルダ名のみとします。
     */
    public static function getCreateForm(): array
    {
        return [
            TextInput::make('title')
                ->label(__('ledger.folder.title'))
                ->required()
                ->maxLength(255),
        ];
    }

    /**
     * ツリーの各フォルダを編集する際のモーダルフォームを定義します。
     */
    public static function getEditForm(): array
    {
        return static::getCreateForm();
    }

    /**
     * フォルダ作成時に、作成者と更新者を自動で設定します。
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creator_id'] = auth()->id();
        $data['modifier_id'] = auth()->id();

        return $data;
    }

    /**
     * フォルダ更新時に、更新者を自動で設定します。
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['modifier_id'] = auth()->id();

        return $data;
    }

    /**
     * ツリーの各フォルダに表示する情報を定義します。
     * 元のテーブル定義を参考にしています。
     */
    public static function getInfolistColumns(): array
    {
        return [
            TextEntry::make('title')
                ->label(__('ledger.folder.title')),
            TextEntry::make('creator.name')
                ->label(__('ledger.creator.name')),
            TextEntry::make('modifier.name')
                ->label(__('ledger.modifier.name')),
            // フォルダに直接紐づくロールをバッジで表示する例
            TextEntry::make('roles.name')
                ->label(__('ledger.settings.roles'))
                ->badge(),
        ];
    }

    /**
     * ツリーの各フォルダに表示するアクションを定義します。
     */
    public function getTreeActions(): array
    {
        return [
            // 詳細な編集は、既存の編集ページに遷移させます
            Actions\EditAction::make()
                ->url(fn (Folder $record): string => FolderResource::getUrl('edit', ['record' => $record])),
            Actions\DeleteAction::make(),
            // TrashedFilterがないため、これらのアクションは期待通りに機能しない可能性があります
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
