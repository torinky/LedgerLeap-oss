<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    public static bool $shouldRegisterNavigation = false;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function form(Form $form): Form
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
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn(string $context): bool => $context === 'create')
                    ->minLength(8)
                    ->same('passwordConfirmation')
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => Hash::make($state)),
                Forms\Components\TextInput::make('passwordConfirmation')
                    ->password()
                    ->label('Password Confirmation')
                    ->required(fn(string $context): bool => $context === 'create')
                    ->minLength(8)
                    ->dehydrated(false),
                Forms\Components\Select::make('roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->afterStateUpdated(function ($state, $record) {
                        if ($record) {
                            $organization = $record->primaryOrganization();
                            foreach ($state as $roleId) {
                                $record->assignRole(Role::find($roleId), $organization);
                            }
                        }
                    }),
                Forms\Components\Select::make('permissions')
                    ->multiple()
                    ->relationship('permissions', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ViewColumn::make('combined_roles_permissions')
                    ->label('Combined Roles & Permissions')
                    ->view('filament.tables.columns.user-combined-roles-permissions'),
                //                Tables\Columns\TextColumn::make('roles.name')->badge(),
                //                Tables\Columns\TextColumn::make('permissions.name')->badge(),
                Tables\Columns\TextColumn::make('primary_organization')
                    ->label('primary organization')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $primaryOrganization = $record->PrimaryOrganization();
                        if ($primaryOrganization) {
                            return $primaryOrganization->name;
                        }

                        return null;
                    })
                    ->colors(['primary'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('organizations')
                    ->label('organizations')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->organizations()->pluck('name', 'is_primary'))
                    ->colors(['info'])
                    ->searchable(),
                /*                Tables\Columns\TextColumn::make('organizations')
                    ->formatStateUsing(function ($state, $record) {
                        $getBadgeHtml = function ($org) {
                            $style = $org->pivot->is_primary
                                ? '--c-50:var(--success-50);--c-400:var(--success-400);--c-600:var(--success-600);'
                                : '--c-50:var(--gray-50);--c-400:var(--gray-400);--c-600:var(--gray-600);';
                            $colorClass = 'fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30';
                            $label = e($org->name).($org->pivot->is_primary ? ' (Primary)' : '');

                            return "<span style='{$style}' class='fi-badge inline-flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 {$colorClass}'>{$label}</span>";
                        };

                        return new HtmlString(
                            $record->organizations->map($getBadgeHtml)->implode(' ')
                        );
                    })
                    ->html(),*/
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrganizationRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['roles', 'permissions', 'organizations.roles', 'organizations.permissions']);
    }
}
