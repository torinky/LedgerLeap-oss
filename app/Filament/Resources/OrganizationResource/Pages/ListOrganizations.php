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
use Illuminate\Database\Eloquent\Builder;

//use CubeAgency\FilamentTreeView\Resources\Pages\TreeViewRecords;

//class ListOrganizations extends TreeViewRecords
class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                return Organization::withDepth()->defaultOrder();
            })
            ->recordUrl(fn($record) => null)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        $depth = $record->depth ?? 0;
                        $prefix = str_repeat('— ', $depth);

                        return $prefix . $record->name;
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');

    }
}
