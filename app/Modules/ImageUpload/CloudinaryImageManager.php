<?php
declare(strict_types=1);

namespace App\Modules\ImageUpload;


use Cloudinary\Cloudinary;

class CloudinaryImageManager implements ImageManagerInterface
{
//    private $cloudhinary;

    public function __construct(private Cloudinary $cloudinary)
    {
//        $this->cloudinary = $cloudinary;
    }

    public function save($file): string
    {
        return $this->cloudinary->uploadApi()->upload(is_string($file) ? $file : $file->getRealPath())['public_id'];
    }

    public function delete(string $name): void
    {
        $this->cloudinary->uploadApi()->destroy($name);
    }
}
