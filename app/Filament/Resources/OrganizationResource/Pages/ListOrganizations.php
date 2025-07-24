<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use App\Models\Organization;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Contracts\Support\Htmlable; // この行を追加します
use Kalnoy\Nestedset\QueryBuilder;
use Studio15\FilamentTree\Components\TreePage;

class ListOrganizations extends TreePage
{
    protected static string $resource = OrganizationResource::class;

    /**
     * ページのタイトルを定義します。
     *
     * @return string|Htmlable
     */
    public function getTitle(): string | Htmlable
    {
        // 関連するリソースクラスから複数形のモデルラベルを取得して返します。
        // これにより、OrganizationResourceで定義された翻訳が適用されます。
        return __('ledger.organization');
    }

    /**
     * TreePageで必須のメソッド：使用するモデルを返します。
     */
    public static function getModel(): string|QueryBuilder
    {
        return Organization::class;
    }

    /**
     * ツリー上で新しいノードを作成する際のモーダルフォームを定義します。
     * TreePageで必須のメソッドです。
     */
    public static function getCreateForm(): array
    {
        // parent_id はツリー構造から自動的に設定されるため、フォームに含める必要はありません。
        return [
            TextInput::make('org_id')
                ->label('Organization ID')
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('name')
                ->label(__('ledger.organizations.name'))
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label(__('ledger.description'))
                ->maxLength(65535),
            Select::make('roles')
                ->label(__('ledger.settings.roles'))
                ->multiple()
                ->relationship('roles', 'name'),
        ];
    }

    /**
     * ツリーの各ノードを編集する際のモーダルフォームを定義します。
     * OrganizationResourceのフォーム定義を参考にしています。
     */
    public static function getEditForm(): array
    {
        // 作成フォームと編集フォームが同じ内容なので、getCreateFormを再利用します。
        return static::getCreateForm();
    }

    /**
     * ツリーの各ノードに表示する情報を定義します。
     * 元のテーブルカラム定義を参考にしています。
     */
    public static function getInfolistColumns(): array
    {
        return [
            TextEntry::make('name')
                ->label(__('ledger.organizations.name')),
            TextEntry::make('org_id')
                ->label('Organization ID'),
            // 複雑なViewColumnの代わりに、関連ロールをバッジで表示する例
            TextEntry::make('roles.name')
                ->label(__('ledger.settings.roles'))
                ->badge(),
            TextEntry::make('description')
                ->label(__('ledger.description'))
                ->limit(50),
        ];
    }

    /**
     * ツリーの各ノードに表示するアクションを定義します。
     */
    public function getTreeActions(): array
    {
        return [
            // 編集ページに遷移する標準のEditAction
            Actions\EditAction::make()
                ->url(fn (Organization $record): string => OrganizationResource::getUrl('edit', ['record' => $record])),
            Actions\DeleteAction::make(),
            // 注意: TrashedFilterがないため、以下のActionは期待通りに機能しない可能性があります。
            // 必要であれば、クエリをカスタマイズするなどの追加対応が必要です。
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}