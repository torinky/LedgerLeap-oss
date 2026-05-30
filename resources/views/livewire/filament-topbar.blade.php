<div class="flex items-center gap-x-5 flex-nowrap">
    @livewire(\App\Livewire\Common\PageQrCode::class, ['triggerType' => 'filament'])
    <livewire:tenant-switcher-filament :show-folders="false" />
</div>

