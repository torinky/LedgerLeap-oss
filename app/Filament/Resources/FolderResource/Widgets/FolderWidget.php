<?php

namespace App\Filament\Resources\FolderResource\Widgets;

use Filament\Widgets\Widget as BaseWidget;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class FolderWidget extends BaseWidget
{
    protected static string $model = Folder::class;

    protected static int $maxDepth = 2;

    protected ?string $treeTitle = 'FolderWidget';

    protected bool $enableTreeTitle = true;

    public function getViewFormSchema(): array
    {
        return [
            //
        ];
    }

    // INFOLIST, CAN DELETE

    public function getTreeRecordIcon(?Model $record = null): ?string
    {
        return null;
    }

    // CUSTOMIZE ICON OF EACH RECORD, CAN DELETE

    protected function getFormSchema(): array
    {
        return [
            //
        ];
    }

    // CUSTOMIZE ACTION OF EACH RECORD, CAN DELETE

    protected function getTreeActions(): array
    {
        return [
            /*             Action::make('helloWorld')
                             ->action(function () {
                                 Notification::make()->success()->title('Hello World')->send();
                             }),*/
            ViewAction::make(),
            EditAction::make(),
            /*             ActionGroup::make([

                             ViewAction::make(),
                             EditAction::make(),
                         ]),*/
            DeleteAction::make(),
        ];
    }

    // OR OVERRIDE FOLLOWING METHODS
    protected function hasDeleteAction(): bool
    {
        return true;
    }

    protected function hasEditAction(): bool
    {
        return true;
    }

    protected function hasViewAction(): bool
    {
        return true;
    }
}
