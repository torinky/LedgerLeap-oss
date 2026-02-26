@props(['isDirty', 'label', 'wireClick' => 'save', 'spinner' => 'save', 'class' => 'btn-primary', 'type' => 'submit'])

<x-mary-button :label="$label"
               :type="$type"
               :wire:click="$wireClick"
               icon="o-pencil-square"
               :class="$class"
               :spinner="$spinner"
               :disabled="!$isDirty"
/>
