<?php

declare(strict_types=1);

namespace Studio15\FilamentTree\Components;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\QueryBuilder;
use Livewire\Attributes\On;
use Livewire\Component;
use LogicException;
use Studio15\FilamentTree\MoveItem;

/**
 * Drag-n-drop listener
 */
final class Move extends Component
{
    /**
     * @var class-string<TreePage>
     */
    public string $component;

    /**
     * @param positive-int $id
     * @param numeric-string|null $ancestor
     * @param numeric-string|null $parent
     * @param non-negative-int $from
     * @param non-negative-int $to
     */
    #[On('filament-tree-moved')]
    public function moveTreeItem(int $id, ?string $ancestor, ?string $parent, int $from, int $to): void
    {
        if ($ancestor === '') {
            $ancestor = null;
        }

        if ($parent === '') {
            $parent = null;
        }

        $modelClass = $this->component::getModel();

        $query = $modelClass instanceof QueryBuilder
            ? $modelClass
            : $modelClass::query();

        /** @var Model $node */
        $node = $query->findOrFail($id);

        try {
            app(MoveItem::class)(
                node: $node,
                parent: $parent === null ? null : (int) $parent,
                from: $from,
                to: $to,
            );
        } catch (LogicException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            return;
        }

        $this->dispatch("filament-tree-refresh.{$id}");

        if ($ancestor !== null) {
            $this->dispatch("filament-tree-refresh.{$ancestor}");
        }

        if ($parent !== null) {
            $this->dispatch("filament-tree-refresh.{$parent}");
        }

        // Update root tree
        if ($ancestor === null || $parent === null) {
            $this->dispatch('filament-tree-updated');
        }

        Notification::make()
            ->success()
            ->title(__('filament-tree::translations.item_moved'))
            ->send();
    }

    public function render(): View
    {
        return view('filament-tree::move');
    }
}
