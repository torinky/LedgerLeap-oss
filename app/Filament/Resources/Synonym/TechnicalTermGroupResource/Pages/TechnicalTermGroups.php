<?php

namespace App\Filament\Resources\Synonym\TechnicalTermGroupResource\Pages;

use App\Filament\Resources\Synonym\TechnicalTermGroupResource;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;

class TechnicalTermGroups extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TechnicalTermGroupResource::class;

    protected static string $view = 'filament.resources.synonym.technical-term-group-resource.pages.technical-term-groups';
}
