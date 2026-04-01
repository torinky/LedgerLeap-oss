<?php

declare(strict_types=1);

namespace Studio15\FilamentTree\Components;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Kalnoy\Nestedset\QueryBuilder;
use Livewire\Component;
use Throwable;

/**
 * Footer component
 */
final class Footer extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    /**
     * @var class-string<TreePage>
     */
    public string $component;

    public function fixTreeAction(): Action
    {
        return Action::make('fixTree')
            ->label(__('filament-tree::translations.fix_tree'))
            ->icon('heroicon-s-wrench')
            ->action(function (Action $action): void {
                $this->dispatch('filament-tree-updated');

                $query = $this->component::getModel();

                try {
                    $query instanceof QueryBuilder
                        ? $query->fixTree()
                        : $query::fixTree();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->danger()
                        ->title($e->getMessage())
                        ->send();

                    $action->failure();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('filament-tree::translations.tree_fixed'))
                    ->send();

                $action->success();
            });
    }

    public function render(): View
    {
        return view('filament-tree::footer');
    }
}
