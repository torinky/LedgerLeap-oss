<?php

namespace App\Filament\Resources;

use Althinect\FilamentSpatieRolesPermissions\Resources\PermissionResource as BasePermissionResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermissionResource extends BasePermissionResource
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        // 親クラスのformメソッドを呼び出す
        $parentForm = parent::form($form);

        return $parentForm->schema([
            ...$parentForm->getSchema(), // 親のスキーマを取得

            TextInput::make('description') // descriptionフィールドを追加
            ->label('Description') // ラベルを設定
            ->nullable() // nullableにする場合
            ->placeholder('Enter a description...'), // プレースホルダーを設定
        ]);
    }

    public static function table(Table $table): Table
    {
        // 親クラスのtableメソッドを呼び出す
        $parentTable = parent::table($table);

        return $parentTable->columns([
            ...$parentTable->getColumns(), // 親のカラムを取得

            TextColumn::make('description') // descriptionカラムを追加
            ->label('Description') // ラベルを設定
            ->sortable()
                ->searchable(),
        ]);
    }
}
