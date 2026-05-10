<?php

declare(strict_types=1);

namespace Studio15\FilamentTree\Components;

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;
use Kalnoy\Nestedset\QueryBuilder;
use Livewire\Attributes\On;
use Studio15\FilamentTree\Concerns\InteractsWithTree;
use Studio15\FilamentTree\Exception\InvalidModel;

/**
 * Abstract panel page
 */
abstract class TreePage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bars-3-bottom-right';

    protected string $view = 'filament-tree::tree';

    /**
     * Model class or scoped query
     *
     * @see https://github.com/lazychaser/laravel-nestedset#scoping
     *
     * @return class-string<Model>|QueryBuilder
     */
    abstract public static function getModel(): QueryBuilder|string;

    /**
     * @return list<Field>
     */
    abstract public static function getEditForm(): array;

    /**
     * @return list<Field>
     */
    abstract public static function getCreateForm(): array;

    /**
     * @return list<Entry>
     */
    public static function getInfolistColumns(): array
    {
        return [];
    }

    /**
     * @throws InvalidModel
     */
    public function mount(): void
    {
        $modelClass = static::getModel();

        if ($modelClass instanceof QueryBuilder) {
            $modelClass = $modelClass->getModel()::class;
        }

        $concerns = class_uses($modelClass);

        if (! \in_array(NodeTrait::class, $concerns, true)) {
            throw new InvalidModel(
                \sprintf('Model should use %s', NodeTrait::class),
            );
        }

        if (! \in_array(InteractsWithTree::class, $concerns, true)) {
            throw new InvalidModel(
                \sprintf('Model should use %s', InteractsWithTree::class),
            );
        }
    }

    #[On('filament-tree-updated')]
    public function refresh(): void
    {
        // Re-render component
    }

    protected function getViewData(): array
    {
        $modelClass = static::getModel();
        $query = $modelClass instanceof QueryBuilder
            ? $modelClass->defaultOrder()
            : $modelClass::query()->defaultOrder();

        return [
            'tree' => $query->withDepth()->get()->toTree(),
        ];
    }
}
