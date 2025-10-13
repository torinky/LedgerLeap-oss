<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Models\User; // 追加
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn; // 追加
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model; // 追加
use Illuminate\Support\Facades\DB; // 追加

class UserRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ledger.user');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.user');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.user');
    }

    public function form(Form $form): Form
    {
        // EditActionでは中間テーブルの情報(is_primary)のみを編集
        return $form
            ->schema([
                Forms\Components\Toggle::make('is_primary')
                    ->label(__('ledger.organizations.primary'))
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ledger.name')), // 翻訳キーを修正
                Tables\Columns\TextColumn::make('email'),

                // IconColumn を ToggleColumn に変更し、排他制御ロジックを追加
                ToggleColumn::make('pivot.is_primary')
                    ->label(__('ledger.organizations.primary'))
                    ->updateStateUsing(function (RelationManager $livewire, Model $record, bool $state) {
                        // トランザクションを開始して、処理の原子性を保証
                        DB::transaction(function () use ($livewire, $record, $state) {
                            /** @var User $user */
                            $user = $record;

                            // トグルが ON にされた場合のみ排他制御を実行
                            if ($state) {
                                // このユーザーの他の主所属をすべて解除
                                DB::table('user_organizations')
                                    ->where('user_id', $user->id)
                                    ->update(['is_primary' => false]);
                            }

                            // クリックされた組織とユーザーの中間テーブル情報を更新
                            $user->organizations()->updateExistingPivot($livewire->getOwnerRecord()->id, [
                                'is_primary' => $state,
                            ]);
                        });
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect() // 検索パフォーマンス向上のため追加
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->multiple()
                            ->searchable(),
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('ledger.organizations.primary'))
                            ->default(false),
                    ])
                    // ▼▼▼ afterコールバックを追加して排他制御 ▼▼▼
                    ->after(function (RelationManager $livewire, array $data) {
                        if ($data['is_primary']) {
                            // アタッチされた各ユーザーに対して排他制御を行う
                            foreach ($data['recordId'] as $userId) {
                                // 他の組織の is_primary を false にする
                                DB::table('user_organizations')
                                    ->where('user_id', $userId)
                                    ->where('organization_id', '!=', $livewire->getOwnerRecord()->id)
                                    ->update(['is_primary' => false]);
                            }
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    // ▼▼▼ afterコールバックを追加して排他制御 ▼▼▼
                    ->after(function (Model $record, array $data) {
                        if ($data['is_primary']) {
                            // $record は編集された User モデル
                            DB::table('user_organizations')
                                ->where('user_id', $record->id)
                                ->where('organization_id', '!=', $this->getOwnerRecord()->id)
                                ->update(['is_primary' => false]);
                        }
                    }),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }

    // このリレーションマネージャーでは不要なため削除
    // protected function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    // {
    //     return parent::getEloquentQuery()->with('organizations.ancestors');
    // }
}
