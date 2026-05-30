<?php

namespace Tests\Feature\Components;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpandableContentComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_expandable_content_component_renders(): void
    {
        $content = '<p>This is a test content that should be expandable.</p>';

        $view = $this->blade(
            '<x-expandable-content :content="$content" />',
            ['content' => $content]
        );

        // コンポーネントが正しくレンダリングされることを確認
        $view->assertSee('x-data', false);
        $view->assertSee('expanded', false);
        $view->assertSee('x-intersect.once.threshold.10="activate()"', false);
        $view->assertSee('This is a test content that should be expandable.', false);
        $view->assertDontSee('showToggleHint', false);
        $view->assertDontSee('skipMeasurement', false);
    }

    public function test_expandable_content_with_custom_max_height(): void
    {
        $content = '<p>Test content</p>';

        $view = $this->blade(
            '<x-expandable-content :content="$content" max-height="10rem" />',
            ['content' => $content]
        );

        // カスタムの最大高さが適用されていることを確認
        $view->assertSee('10rem', false);
    }

    public function test_expandable_content_includes_show_more_button(): void
    {
        $content = '<p>Test content</p>';

        $view = $this->blade(
            '<x-expandable-content :content="$content" />',
            ['content' => $content]
        );

        // ボタンが含まれていることを確認
        $view->assertSee('btn', false);
        $view->assertSee('chevron', false);
    }

    public function test_expandable_content_short_content_keeps_measurement_based_markup(): void
    {
        $content = '<p>短文セル</p>';

        $view = $this->blade(
            '<x-expandable-content :content="$content" />',
            ['content' => $content]
        );

        $view->assertSee('短文セル', false);
        $view->assertSee('x-show="showToggle"', false);
        $view->assertSee('x-intersect.once.threshold.10="activate()"', false);
        $view->assertDontSee('showToggleHint', false);
        $view->assertDontSee('skipMeasurement', false);
    }
}
