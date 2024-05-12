<div class="space-y-6">

    <div>
        <x-input-label for="w_o_r_d" :value="__('Word')"/>
        <x-text-input wire:model="form.WORD" id="w_o_r_d" name="WORD" type="text" class="mt-1 block w-full"
                      autocomplete="WORD" placeholder="Word"/>
        @error('form.WORD')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="pronunciation1" :value="__('Pronunciation1')"/>
        <x-text-input wire:model="form.pronunciation1" id="pronunciation1" name="pronunciation1" type="text"
                      class="mt-1 block w-full" autocomplete="pronunciation1" placeholder="Pronunciation1"/>
        @error('form.pronunciation1')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="pronunciation2" :value="__('Pronunciation2')"/>
        <x-text-input wire:model="form.pronunciation2" id="pronunciation2" name="pronunciation2" type="text"
                      class="mt-1 block w-full" autocomplete="pronunciation2" placeholder="Pronunciation2"/>
        @error('form.pronunciation2')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="category1" :value="__('Category1')"/>
        <x-text-input wire:model="form.category1" id="category1" name="category1" type="text" class="mt-1 block w-full"
                      autocomplete="category1" placeholder="Category1"/>
        @error('form.category1')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="category2" :value="__('Category2')"/>
        <x-text-input wire:model="form.category2" id="category2" name="category2" type="text" class="mt-1 block w-full"
                      autocomplete="category2" placeholder="Category2"/>
        @error('form.category2')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>
    <div>
        <x-input-label for="c_a_n_d_i_d_a_t_e_s" :value="__('Candidates')"/>
        <x-text-input wire:model="form.CANDIDATES" id="c_a_n_d_i_d_a_t_e_s" name="CANDIDATES" type="text"
                      class="mt-1 block w-full" autocomplete="CANDIDATES" placeholder="Candidates"/>
        @error('form.CANDIDATES')
        <x-input-error class="mt-2" :messages="$message"/>
        @enderror
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>Submit</x-primary-button>
    </div>
</div>
