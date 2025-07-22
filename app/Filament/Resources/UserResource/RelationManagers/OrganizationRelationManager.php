<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Organization;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Log;

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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('id')
                    ->label(__('ledger.organization'))
                    ->options(Organization::pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Forms\Components\Toggle::make('is_primary')
                    ->label(__('ledger.organizations.primary'))
                    ->default(false)
                    ->afterStateUpdated(function ($state, $livewire, $set) {
                        if ($state) {
                            // 他の組織のis_primaryをfalseに設定
                            $livewire->getOwnerRecord()->organizations()->updateExistingPivot(
                                $livewire->getOwnerRecord()->organizations()->pluck('organization_id'),
                                ['is_primary' => false]
                            );
                            $set('is_primary', true);
                        }
                    }),
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
                Tables\Columns\IconColumn::make('pivot.is_primary')
                    ->boolean()
                    ->label(__('ledger.organizations.primary')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn(Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('ledger.organizations.primary'))
                            ->default(false),
                    ])
                    ->using(function (RelationManager $livewire, array $data): array {
                        $this->handleOrganizationAssociation($livewire->getOwnerRecord()->id, $data['recordId'], $data['is_primary']);

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (Organization $record, array $data): Organization {
                        $this->handleOrganizationAssociation($this->getOwnerRecord()->id, $record->id, $data['is_primary']);

                        return $record;
                    }),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }

    protected function handleOrganizationAssociation(int $userId, int $organizationId, bool $isPrimary): void
    {
        $user = User::findOrFail($userId);

        // ユーザーと組織を関連付ける（または既存の関連を更新する）
        $user->organizations()->syncWithoutDetaching([
            $organizationId => ['is_primary' => $isPrimary],
        ]);

        if ($isPrimary) {
            // 他の組織のis_primaryをfalseに設定
            $user->organizations()
                ->where('organization_id', '!=', $organizationId)
                ->update(['is_primary' => false]);
        }

        // ログ出力
        Log::info('Organization association updated', [
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'is_primary' => $isPrimary,
        ]);
    }
}
