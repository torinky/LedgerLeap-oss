<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.api_token_manager_title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.name')),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(__('admin.last_used_at'))
                    ->dateTime(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.created_at'))
                    ->dateTime(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label(__('admin.create_api_token'))
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin.token_name'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        $token = $this->getOwnerRecord()->createToken($data['name']);

                        Notification::make()
                            ->title(__('admin.api_token_created'))
                            ->body(__('admin.api_token_body').$token->plainTextToken)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
