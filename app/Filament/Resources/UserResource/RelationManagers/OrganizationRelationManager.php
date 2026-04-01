<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrganizationRelationManager extends RelationManager
{
    protected static string $relationship = 'organizations';

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

    public function form(Schema $schema): Schema
    {
        // この form メソッドは EditAction で使用されます
        return $schema
            ->schema([
                // EditAction のフォームでは Select は不要なため、is_primary のみに絞ります
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
                    ->label(__('ledger.organizations.name'))
                    ->searchable(),

                ToggleColumn::make('pivot.is_primary')
                    ->label(__('ledger.organizations.primary'))
                    // afterStateUpdated の代わりに updateStateUsing を使用して更新ロジックを上書き
                    ->updateStateUsing(function (RelationManager $livewire, Model $record, bool $state) {
                        // トランザクションを開始して、処理の原子性を保証
                        DB::transaction(function () use ($livewire, $record, $state) {
                            /** @var User $user */
                            $user = $livewire->getOwnerRecord();

                            // トグルが ON にされた場合のみ排他制御を実行
                            if ($state) {
                                // このユーザーの他の主所属をすべて解除
                                $user->organizations()
                                    ->wherePivot('is_primary', true)
                                    ->where('organization_id', '!=', $record->id)
                                    ->update(['is_primary' => false]);
                            }

                            // クリックされた組織の中間テーブル情報を更新
                            $user->organizations()->updateExistingPivot($record->id, [
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
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('ledger.organizations.primary'))
                            ->default(false),
                    ])
                    // afterコールバック内の排他制御ロジックは引き続き必要
                    ->after(function (RelationManager $livewire, array $data) {
                        if ($data['is_primary']) {
                            $user = $livewire->getOwnerRecord();
                            $user->organizations()
                                ->where('organization_id', '!=', $data['recordId'])
                                ->update(['is_primary' => false]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    // afterコールバック内の排他制御ロジックは引き続き必要
                    ->after(function (Model $record, array $data) {
                        if ($data['is_primary']) {
                            $this->getOwnerRecord()->organizations()
                                ->where('organization_id', '!=', $record->id)
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
}
