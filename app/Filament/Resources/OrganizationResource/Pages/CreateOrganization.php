<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use Filament\Resources\Pages\CreateRecord;

//use CubeAgency\FilamentTreeView\Resources\Pages\CreateTreeViewRecord;

//class CreateOrganization extends CreateTreeViewRecord
class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;
}
