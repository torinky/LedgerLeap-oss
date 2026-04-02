<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                // is_primary トグルはロールとの関連性では不要なため削除
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('organizations') // カラム名をリレーション名に変更
                    ->label(__('ledger.organizations.title'))
                    ->badge() // 配列の各要素をバッジとして表示
                    ->getStateUsing(function ($record) { // $record は User モデルのインスタンス
                        // ユーザーが所属する各組織の full_name アクセサを呼び出して取得
                        return $record->organizations->pluck('full_name');
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\AttachAction::make()
                    ->form(fn (\Filament\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->multiple() // 複数ユーザーを選択可能にする
                            ->searchable(), // ユーザーを検索可能にする
                        // is_primary トグルはロールとの関連性では不要なため削除
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }

    protected function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('organizations.ancestors');
    }
}
