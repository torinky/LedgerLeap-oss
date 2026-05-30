<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class LedgerFormTextareaComponentTest extends TestCase
{
    public function test_textarea_component_renders_plain_textarea_in_demo_mode(): void
    {
        $columnDefine = (object) [
            'id' => 7,
            'name' => '複数行テキスト',
            'required' => false,
            'options' => [
                'placeholder' => '複数行で入力',
                'hint' => '補足説明',
            ],
        ];

        $view = $this->withViewErrors([])->blade('<x-ledger.form.textarea :columnDefine="$columnDefine" :isDemo="true" />', [
            'columnDefine' => $columnDefine,
        ]);

        $view->assertSee('<textarea', false);
        $view->assertSee('name="content[7]"', false);
        $view->assertSee('rows="5"', false);
        $view->assertSee('複数行で入力');
        $view->assertDontSee('wire:model.defer="content.7"', false);
    }
}
