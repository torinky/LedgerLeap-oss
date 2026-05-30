<?php

namespace App\Filament\Traits;

use App\Models\Tenant;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * テナントとフォルダの選択フォームを提供するトレイト
 */
trait HasFolderSelection
{
    /**
     * テナントとフォルダの選択用フォームコンポーネントを取得
     */
    protected function getFolderSelectionForm(): array
    {
        return [
            Select::make('tenant_id')
                ->label(__('ledger.tenant'))
                ->options(
                    Tenancy::central(function () {
                        return Tenant::all()->mapWithKeys(function ($tenant) {
                            return [$tenant->id => $tenant->name ?: $tenant->id];
                        });
                    })
                )
                ->live()
                ->afterStateUpdated(function (callable $set) {
                    $set('folder_id', null);
                })
                ->required(),

            SelectTree::make('folder_id')
                ->label(__('ledger.folder.title'))
                ->relationship(
                    relationship: 'folder',
                    titleAttribute: 'display_title',
                    parentAttribute: 'parent_id',
                    modifyQueryUsing: fn (EloquentBuilder $query, callable $get) => Tenancy::central(function () use ($query, $get) {
                        $selectedTenantId = $get('tenant_id');
                        if ($selectedTenantId) {
                            $query->where('tenant_id', $selectedTenantId);
                        }

                        return $query->with('tenant')->orderBy('_lft');
                    })
                )
                ->required()
                ->searchable()
                ->enableBranchNode()
                ->defaultOpenLevel(1),
        ];
    }

    /**
     * テナントによるフィルタを取得
     */
    protected function getTenantFilter(): Filter
    {
        return Filter::make('tenant_id')
            ->form([
                Select::make('value')
                    ->label(__('ledger.tenant'))
                    ->options(
                        Tenancy::central(function () {
                            return Tenant::all()->mapWithKeys(function ($tenant) {
                                return [$tenant->id => $tenant->name ?: $tenant->id];
                            });
                        })
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->query(function (EloquentBuilder $query, array $data): EloquentBuilder {
                if (blank($data['value'])) {
                    return $query;
                }

                return $query->whereHas('folder', function (EloquentBuilder $query) use ($data) {
                    $query->where('tenant_id', $data['value']);
                });
            })
            ->label(__('ledger.tenant'));
    }

    /**
     * フォルダによるフィルタを取得
     */
    protected function getFolderFilter(): Filter
    {
        return Filter::make('folder_id')
            ->form([
                Select::make('value')
                    ->label(__('ledger.folder.title'))
                    ->relationship('folder', 'title')
                    ->searchable()
                    ->multiple()
                    ->preload(),
            ])
            ->query(function (EloquentBuilder $query, array $data): EloquentBuilder {
                if (blank($data['value'])) {
                    return $query;
                }

                return $query->whereIn('folder_id', $data['value']);
            })
            ->label(__('ledger.folder.title'));
    }
}
