<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Permission;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

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
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('filament-spatie-roles-permissions.navigation_section_group', 'ledger.setting'));
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-spatie-roles-permissions.sort.permission_navigation', 2);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label(__('permission.title'))
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            Select::make('guard_name')
                                ->label(__('role.guard_name'))
                                ->options(config('filament-spatie-roles-permissions.guard_names'))
                                ->default(config('filament-spatie-roles-permissions.default_guard_name'))
                                ->visible(fn () => config('filament-spatie-roles-permissions.should_show_guard', true))
                                ->required(),
                            Select::make('roles')
                                ->multiple()
                                ->label(__('role.name'))
                                ->relationship('roles', 'name')
                                ->preload(config('filament-spatie-roles-permissions.preload_roles', true)),
                        ]),

                        TextInput::make('description') // descriptionフィールドを追加
                            ->label('Description') // ラベルを設定
                            ->nullable() // nullableにする場合
                            ->placeholder('Enter a description...'), // プレースホルダーを設定
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guard_name')
                    ->label(__('role.guard_name'))
                    ->badge(),

                TextColumn::make('name')
                    ->label(__('permission.title'))
                    ->formatStateUsing(function ($record) {
                        return __('permission.name.'.$record->name);
                    })
                    ->searchable(),

                TextColumn::make('description')
                    ->label(__('permission.description'))
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
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
