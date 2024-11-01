<?php

declare(strict_types=1);

namespace App\Modules\ImageUpload;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

interface ImageManagerInterface
{
    /**
     * @param File|UploadedFile|string $file
     */
    public function save($file): string;

    public function delete(string $name): void;
}
