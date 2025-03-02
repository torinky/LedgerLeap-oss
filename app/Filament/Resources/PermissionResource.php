<?php

namespace App\Filament\Resources;

use Althinect\FilamentSpatieRolesPermissions\Resources\PermissionResource as BasePermissionResource;
use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Permission;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class PermissionResource extends BasePermissionResource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getLabel(): string
    {
        return __('ledger.settings.permissions');
        //        return __('filament-spatie-roles-permissions::filament-spatie.section.permission');
    }

    public static function getModelLabel(): string
    {
        return __('ledger.settings.permissions');
    }

    public static function getPluralLabel(): string
    {
        return __('ledger.settings.permissions');
        //        return __('filament-spatie-roles-permissions::filament-spatie.section.permissions');
    }

    public static function form(Form $form): Form
    {
        Log::info('formメソッドが実行されました');

        // 親クラスのformメソッドを呼び出す
        $parentForm = parent::form($form);

        return $form
            ->schema([
                Section::make()
                    ->schema([
                        ...$parentForm->getComponents(), // 親のスキーマを取得

                        TextInput::make('description') // descriptionフィールドを追加
                        ->label('Description') // ラベルを設定
                        ->nullable() // nullableにする場合
                        ->placeholder('Enter a description...'), // プレースホルダーを設定
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        Log::info('tableメソッドが実行されました');
        // 親クラスのtableメソッドを呼び出す
        $parentTable = parent::table($table);

        return $table
            ->columns([
                ...$parentTable->getColumns(), // 親のカラムを取得

                TextColumn::make('name')
                    ->label(__('permission.title'))
                    ->formatStateUsing(function ($record) {
                        return __('permission.name.' . $record->name);
                    })
                    ->searchable(),

                TextColumn::make('description')
                    ->label(__('permission.description'))
                    ->sortable()
                    ->searchable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {

        //        dd('view', auth()->user()->can('view_permissionss', Permission::class));
        return auth()->user()->can('view_permissions', Permission::class);
    }

    public static function canCreate(): bool
    {
        //        dd('create', auth()->user()->can('create_permissions'));
        return auth()->user()->can('create_permissions', Permission::class);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update_permissions', $record);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete_permissions', $record);
    }
}
