@props([
    'announcement' => null,
])

@if (is_array($announcement) && ! empty($announcement))
    @vite(['resources/sass/app.scss'])

    <div class="space-y-4">
        <x-admin.announcement-banner-preview-shell :announcement="$announcement" />
    </div>
@endif