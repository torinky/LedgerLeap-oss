<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Filament\Resources\OrganizationResource;
use App\Models\Organization;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('ledger.organizations.children');
    }
    public static function getModelLabel(): string
    {
        return __('ledger.organization');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.organization');
    }


    public function form(Form $form): Form
    {
        // このフォームは「新規作成」アクションで使用されます
        return $form
            ->schema([
                Forms\Components\TextInput::make('org_id')
                    ->label('Organization ID')
                    ->unique(ignoreRecord: true) // 他の組織とIDが重複しないようにバリデーション
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label(__('ledger.organizations.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label(__('ledger.description'))
                    ->maxLength(65535),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ledger.organizations.name')),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('ledger.description'))
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ledger.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // 既存の組織を子として紐付けるアクション
                Tables\Actions\Action::make('attach_children')
                    ->label(__('ledger.attach_existing_organization')) // 翻訳キーに変更
                    ->icon('heroicon-o-paper-clip')
                    ->form([
                        SelectTree::make('children_ids')
                            ->label(__('ledger.organizations_to_attach_under')) // 翻訳キーに変更
                            ->multiple()
                            ->relationship('parent', 'name', 'parent_id')
                            ->searchable()
                            ->clearable()
                            ->placeholder(__('ledger.select_organizations_to_attach')) // 翻訳キーに変更
                            ->hiddenOptions(function (RelationManager $livewire): array {
                                $ownerRecord = $livewire->getOwnerRecord();
                                // 循環参照を防ぐため、現在の組織、その先祖、そして既に子である組織は選択肢から除外する
                                $ancestorAndSelfIds = $ownerRecord->ancestorsAndSelf($ownerRecord->id)->pluck('id')->toArray();
                                $childrenIds = $ownerRecord->children()->pluck('id')->toArray();

                                return array_merge($ancestorAndSelfIds, $childrenIds);
                            })
                            ->required(),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $ownerRecord = $livewire->getOwnerRecord();
                        // 選択された組織のparent_idを現在の組織のIDに更新する
                        Organization::whereIn('id', $data['children_ids'])
                            ->update(['parent_id' => $ownerRecord->id]);
                    })
                    ->modalWidth('3xl'),
                // 新しい子組織を作成するアクション
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // 子組織の編集画面に直接遷移するアクション
                Tables\Actions\EditAction::make()
                    ->url(fn (Organization $record): string => OrganizationResource::getUrl('edit', ['record' => $record])),

                // 紐付けを解除するアクション (parent_idをnullにする)
                Tables\Actions\Action::make('detach_child')
                    ->label(__('ledger.detach')) // 翻訳キーに変更
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('ledger.detach_confirmation_title')) // 翻訳キーに変更
                    ->modalDescription(__('ledger.detach_confirmation_description')) // 翻訳キーに変更
                    ->action(fn (Organization $record) => $record->update(['parent_id' => null])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}