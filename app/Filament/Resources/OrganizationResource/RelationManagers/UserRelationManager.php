<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UserRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

//    protected static ?string $title= 'Users';

    public static function getTitle($ownerRecord, string $pageClass): string
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
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_primary')
                    ->label(__('ledger.organization.primary'))
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),
                Tables\Columns\TextColumn::make('organizations') // カラム名をリレーション名に変更
                ->label(__('ledger.organization'))
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
                Tables\Actions\AttachAction::make()
                    ->form(fn(Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->multiple() // 複数ユーザーを選択可能にする
                            ->searchable(), // ユーザーを検索可能にする
                        Forms\Components\Toggle::make('is_primary')
                            ->label('Primary Organization')
                            ->default(false),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }

    protected function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('organizations.ancestors');
    }
}
