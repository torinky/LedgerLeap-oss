<input
    wire:model.lazy="content.{{$columnDefine->id}}" id="content[{{$columnDefine->id}}]"
    class="adjustWidth input input-bordered" name="content[{{$columnDefine->id}}]"
    value="{{$this->content[$columnDefine->id] ?? ''}}"
>

@once
    @push('scripts')
        <script>
            document.addEventListener('livewire:load', function () {
                const inputs = document.querySelectorAll('input.adjustWidth');

                function getWidthOfInput(input) {
                    const temp = document.createElement('span');
                    temp.textContent = input.value || input.placeholder;
                    temp.style.position = 'absolute';
                    temp.style.visibility = 'hidden';
                    document.body.appendChild(temp);
                    const width = temp.offsetWidth + 50;
                    document.body.removeChild(temp);
                    return width;
                }

                function adjustInputWidth() {
                    inputs.forEach((input) => {
                        const width = getWidthOfInput(input);
                        input.style.width = width + 'px';
                    });
                }

                window.adjustWidth = function (event) {
                    const input = event.target;
                    const width = getWidthOfInput(input);
                    input.style.width = width + 'px';
                    console.log(input.style.width);
                };

                adjustInputWidth();

                window.addEventListener("keydown", function (event) {
                    event.preventDefault();
                    window.adjustWidth(event);
                });

                Livewire.hook('message.processed', () => {
                    adjustInputWidth();
                });
            });
        </script>
    @endpush
@endonce
