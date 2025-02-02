<div>
    <x-slot name="header">
        <h2 class="font-semibold text-base-content leading-tight">
            {{ __('Notification Settings') }}
        </h2>
    </x-slot>

    <div class="py-2">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-base-100 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-mary-form wire:submit="save">
                        @foreach($notificationTypes as $type)
                            <div class="mb-4">
                                <x-mary-checkbox
                                    wire:model="settings.{{ $type['id'] }}"
                                    :label="$type['name']"
                                    :description="$type['description']"
                                    class="mb-2"
                                />
                            </div>
                        @endforeach

                        <x-slot:actions>
                            <x-mary-button label="{{__('ledger.cancel')}}" class="btn-outline"/>
                            <x-mary-button label="{{__('ledger.save')}}" class="btn-primary" type="submit"
                                           spinner="save"/>
                        </x-slot:actions>
                    </x-mary-form>
                </div>
            </div>
        </div>
    </div>
</div>
