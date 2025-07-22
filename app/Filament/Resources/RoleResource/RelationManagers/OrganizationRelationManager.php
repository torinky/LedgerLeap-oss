<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Models\Organization; // 追加
use CodeWithDennis\FilamentSelectTree\SelectTree; // 追加
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action; // 変更
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrganizationRelationManager extends RelationManager
{
    // Laravelの命名規則に合わせて小文字に変更
    protected static string $relationship = 'organizations';

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    /*
     * Support changing tab title in RelationManager.
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ledger.organization');
    }

    protected static function getModelLabel(): string
    {
        return __('ledger.organization');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('ledger.organization');
    }

    public function form(Form $form): Form
    {
        // このフォームはAttach/Detachのみのため、直接は使用されない
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('ledger.organizations.name')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // 翻訳キーを修正
            ->heading(__('ledger.organization'))
            ->columns([
                TextColumn::make('name')
                    // 翻訳キーを修正
                    ->label(__('ledger.name'))
                    ->searchable(),
            ])
            ->filters([
                //
            ])->headerActions([
                // ▼▼▼ ここから修正 ▼▼▼
                // 標準のAttachActionをカスタムアクションに置き換え
                Action::make('attach')
                    ->label(__('ledger.new_relation_attach')) // より適切な翻訳キーに変更
                    ->icon('heroicon-o-paper-clip')
                    ->form([
                        SelectTree::make('organization_ids')
                            ->label(__('ledger.organization'))
                            ->multiple()
                            ->relationship('parent', 'name', 'parent_id')
                            ->searchable()
                            ->placeholder(__('ledger.select_organizations_to_attach'))
                            // 既に紐付けられている組織は選択肢から除外する
                            ->hiddenOptions(function (RelationManager $livewire): array {
                                return $livewire->getOwnerRecord()->organizations()->pluck('id')->toArray();
                            })
                            ->required(),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        // 選択された組織をロールに紐付ける
                        $livewire->getOwnerRecord()->organizations()->attach($data['organization_ids']);
                    })
                    ->modalWidth('3xl'), // 見やすくするためにモーダルの幅を広げる
                // ▲▲▲ ここまで修正 ▲▲▲
            ])->actions([
                DetachAction::make(),
            ])->bulkActions([
                //
            ]);
    }
}