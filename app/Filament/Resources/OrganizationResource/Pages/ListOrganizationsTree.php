<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use App\Models\Organization;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Contracts\Support\Htmlable;
use Kalnoy\Nestedset\QueryBuilder;
use Studio15\FilamentTree\Components\TreePage;

class ListOrganizationsTree extends TreePage
{
    protected static string $resource = OrganizationResource::class;

    /**
     * ページのタイトルを定義します。
     *
     * @return string|Htmlable
     */
    public function getTitle(): string | Htmlable
    {
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
     */
    public static function getCreateForm(): array
    {
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
     */
    public static function getEditForm(): array
    {
        return static::getCreateForm();
    }

    /**
     * ツリーの各ノードに表示する情報を定義します。
     */
    public static function getInfolistColumns(): array
    {
        return [
            TextEntry::make('name')
                ->label(__('ledger.organizations.name')),
            TextEntry::make('org_id')
                ->label('Organization ID'),
            TextEntry::make('roles.name')
                ->label(__('ledger.settings.roles'))
                ->badge(),
            TextEntry::make('description')
                ->label(__('ledger.description'))
                ->limit(50),
        ];
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('list_view')
                ->label('リスト表示')
                ->url(OrganizationResource::getUrl('index')),
        ];
    }

}