<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class CapabilityManifestRepository
{
    public function __construct(
        private readonly ?string $manifestPath = null,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        $path = $this->manifestPath ?? resource_path('ai/capabilities');

        if (! File::isDirectory($path)) {
            return collect();
        }

        return collect(File::files($path))
            ->filter(fn ($file) => $file->getExtension() === 'yaml')
            ->sortBy(fn ($file) => $file->getFilename())
            ->map(function ($file) {
                /** @var array<string, mixed> $manifest */
                $manifest = Yaml::parseFile($file->getPathname()) ?? [];

                return array_merge($manifest, [
                    'source_file' => $file->getFilename(),
                ]);
            })
            ->values();
    }

    /**
     * @param  array<int, string>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function findByIds(array $ids): Collection
    {
        if ($ids === []) {
            return $this->all();
        }

        $lookup = collect($ids)
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->values();

        return $this->all()
            ->filter(fn (array $manifest) => $lookup->contains(Arr::get($manifest, 'id')))
            ->values();
    }

    /**
     * @param  array<int, string>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function active(array $ids = []): Collection
    {
        return $this->findByIds($ids)
            ->filter(fn (array $manifest) => Arr::get($manifest, 'status') === 'active')
            ->values();
    }

    /**
     * @param  array<int, string>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function planned(array $ids = []): Collection
    {
        return $this->findByIds($ids)
            ->filter(fn (array $manifest) => Arr::get($manifest, 'status') === 'planned')
            ->values();
    }
}
