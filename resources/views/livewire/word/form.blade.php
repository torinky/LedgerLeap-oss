<div class="space-y-6">

    <div>
        <x-input-label for="wordid" :value="__('Wordid')"/>
        <x-text-input wire:model="form.wordid" id="wordid" name="wordid" type="text" class="mt-1 block w-full"
                      autocomplete="wordid" placeholder="Wordid"/>
        @error('form.wordid')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="lang" :value="__('Lang')"/>
        <x-text-input wire:model="form.lang" id="lang" name="lang" type="text" class="mt-1 block w-full"
                      autocomplete="lang" placeholder="Lang"/>
        @error('form.lang')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="lemma" :value="__('Lemma')"/>
        <x-text-input wire:model="form.lemma" id="lemma" name="lemma" type="text" class="mt-1 block w-full"
                      autocomplete="lemma" placeholder="Lemma"/>
        @error('form.lemma')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="pron" :value="__('Pron')"/>
        <x-text-input wire:model="form.pron" id="pron" name="pron" type="text" class="mt-1 block w-full"
                      autocomplete="pron" placeholder="Pron"/>
        @error('form.pron')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="pos" :value="__('Pos')"/>
        <x-text-input wire:model="form.pos" id="pos" name="pos" type="text" class="mt-1 block w-full" autocomplete="pos"
                      placeholder="Pos"/>
        @error('form.pos')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>Submit</x-primary-button>
    </div>
</div>
