<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use App\Models\Organization;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    /**
     * ページのタイトルを定義します。
     */
    public function getTitle(): string
    {
        return __('ledger.organization');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
            Actions\Action::make('tree_view')
                ->label(__('ledger.views.tree'))
                ->color('info')
                ->icon('heroicon-o-share')
                ->url(OrganizationResource::getUrl('tree')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Organization::withDepth()->orderBy('_lft'))
            ->recordUrl(fn ($record) => null)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->getStateUsing(function (Organization $record) {
                        return str_repeat('・', $record->depth).' '.$record->name;
                    }),
                Tables\Columns\TextColumn::make('org_id')
                    ->label('Organization ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('ledger.description'))
                    ->limit(50),
                Tables\Columns\ViewColumn::make('combined_roles_permissions')
                    ->label(__('role.combined_roles_and_permissions'))
                    ->view('filament.tables.columns.user-combined-roles-permissions') // User用ビューを再利用
                //                    ->wrap()
                ,
                Tables\Columns\TextColumn::make('parent.name')
                    ->label(__('ledger.organizations.parent')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ad_last_synced_at')
                    ->label(__('ledger.ad_last_synced_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                //                Tables\Columns\TextColumn::make('roles.name')->badge(),
                /*                Tables\Columns\TextColumn::make('direct_roles')
                    ->label('Direct Roles')
                    ->badge()
                    ->getStateUsing(fn(Organization $record) => $record->getDirectRoles()->pluck('name'))
                    ->colors(['primary'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('inherited_roles')
                    ->label('Inherited Roles')
                    ->badge()
                    ->getStateUsing(fn(Organization $record) => $record->getInheritedRoles()->pluck('name'))
                    ->colors(['info'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('permissions.name')->badge(),
                Tables\Columns\ViewColumn::make('permissions')
                    ->label('Permissions')
                    ->view('filament.tables.columns.permissions-column')*/
            ])->filters([
                Tables\Filters\TrashedFilter::make(),
                Filter::make('tree')
                    ->form([
                        SelectTree::make('parent_id')
                            ->relationship('parent', 'name', 'parent_id')
                            ->independent(false)
                            ->enableBranchNode(),
                    ])
                    ->query(function ($query, $data) {
                        if ($data['parent_id']) {
                            $query->where('parent_id', $data['parent_id'])->orWhere('id', $data['parent_id']);
                        }
                    }),

            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\ForceDeleteAction::make(),
                Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
                Actions\ForceDeleteBulkAction::make(),
                Actions\RestoreBulkAction::make(),
            ]);
        //            ->reorderable('sort_order')
        //            ->defaultSort('sort_order')

    }
}
