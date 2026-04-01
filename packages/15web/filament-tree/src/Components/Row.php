<?php

declare(strict_types=1);

namespace Studio15\FilamentTree\Components;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NestedSet;
use Kalnoy\Nestedset\QueryBuilder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;
use Livewire\Features\SupportEvents\Event;
use RuntimeException;
use Studio15\FilamentTree\Components\Form\ParentSelect;

/**
 * Tree node component
 */
final class Row extends Component implements HasForms, HasActions, HasInfolists
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithInfolists;

    public Model $row;

    /**
     * Value for dynamic session key evaluation
     */
    public mixed $rowId;

    public mixed $parentId = null;

    /**
     * @var class-string<TreePage>
     */
    public string $component;

    #[Session(key: 'collapsed-{rowId}')]
    public bool $collapsed = true;

    public function mount(): void
    {
        $this->parentId = $this->row->getAttribute(NestedSet::PARENT_ID);
    }

    #[Computed]
    public function canBeDeleted(): bool
    {
        if (config('filament-tree.allow-delete-parent') === false
            && $this->row->children->isNotEmpty()
        ) {
            return false;
        }

        return !(config('filament-tree.allow-delete-root') === false && $this->row->children->isNotEmpty() && $this->row->isRoot());
    }

    public function editAction(): EditAction
    {
        $form = $this->component::getEditForm();

        if (config('filament-tree.show-parent-select-while-edit')) {
            $model = $this->component::getModel();

            array_unshift(
                $form,
                ParentSelect::make($model instanceof QueryBuilder ? $model : $model::query()),
            );
        }

        return EditAction::make()
            ->record(fn (array $arguments): Model => $this->row)
            ->form($form)
            ->after(function (Model $record): void {
                if (!config('filament-tree.show-parent-select-while-edit')) {
                    return;
                }

                $originalParentId = $this->parentId;
                $updatedParentId = $record->getAttribute('parent_id');

                if ($originalParentId !== null) {
                    $this->dispatch("filament-tree-refresh.{$originalParentId}");
                }

                if ($updatedParentId !== null) {
                    $this->dispatch("filament-tree-refresh.{$updatedParentId}");
                }

                // Update root tree
                if ($updatedParentId === null || $originalParentId === null) {
                    $this->dispatch('filament-tree-updated');
                }
            })
            ->icon('heroicon-m-pencil-square')
            ->labeledFrom('md');
    }

    public function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->requiresConfirmation()
            ->before(function (Model $record): void {
                if (!$this->canBeDeleted()) {
                    throw new RuntimeException('Cannot delete tree node.');
                }
            })
            ->record(fn (array $arguments): Model => $this->row)
            ->after(fn (): Event => $this->dispatch('filament-tree-updated'))
            ->icon('heroicon-m-trash')
            ->labeledFrom('md');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->row)
            ->schema($this->component::getInfolistColumns())
            ->view('filament-tree::infolist');
    }

    #[On('filament-tree-refresh.{rowId}')]
    public function refreshNode(): void
    {
        // Re-render component
    }

    public function render(): View
    {
        return view('filament-tree::row');
    }
}
