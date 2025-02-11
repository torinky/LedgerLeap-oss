<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

class DiffDisplay extends Component
{
    public $attribute;
    public $old;
    public $new;
    public $mode;
    public $showLabel;
    public $oldHtml; // 追加
    public $newHtml; // 追加

    /**
     * Create a new component instance.
     *
     * @param string $attribute
     * @param mixed $old
     * @param mixed $new
     * @param string $mode
     * @param bool $showLabel
     * @return void
     */
    public function __construct(string $attribute, $old, $new, string $mode = 'table', bool $showLabel = true)
    {
        $this->attribute = $attribute;
        $this->old = $old;
        $this->new = $new;
        $this->mode = $mode;
        $this->showLabel = $showLabel;
        $this->prepareDiff(); // 差分を計算してプロパティに格納
    }

    protected function prepareDiff()
    {
        $diff = $this->calculateDiff();
        $oldLines = [];
        $newLines = [];
        $lines = explode("\n", $diff);

        foreach ($lines as $line) {
            if (str_starts_with($line, '+')) {
                $newLines[] = '<span class="text-success">' . e(substr($line, 1)) . '</span>';
            } elseif (str_starts_with($line, '-')) {
                $oldLines[] = '<span class="text-error">' . e(substr($line, 1)) . '</span>';
            } elseif (str_starts_with($line, '@@')) {
                // Skip context lines
                continue;
            } else {
                $oldLines[] = e($line);
                $newLines[] = e($line);
            }
        }

        $this->oldHtml = implode('<br>', $oldLines);
        $this->newHtml = implode('<br>', $newLines);
    }

    protected function calculateDiff()
    {
        // 文字列の場合は JSON デコードを試みる
        if (is_string($this->old)) {
            $this->old = json_decode($this->old, true);
        }
        if (is_string($this->new)) {
            $this->new = json_decode($this->new, true);
        }

        // 配列/オブジェクトの場合は JSON 文字列に変換
        if (is_array($this->old) || is_object($this->old)) {
            $this->old = json_encode($this->old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        if (is_array($this->new) || is_object($this->new)) {
            $this->new = json_encode($this->new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        if ($this->old === null) {
            $this->old = '';
        }
        if ($this->new === null) {
            $this->new = '';
        }
        // 差分を計算
        // $differ = new Differ(new UnifiedDiffOutputBuilder(
        //     "--- Original\n+++ New\n", // ヘッダー (Unified Diff 形式)
        //     true // コンテキスト行を含めるかどうか
        // ));
        // UnifiedDiffOutputBuilder のコンストラクタで空のヘッダーを渡す
        $differ = new Differ(new UnifiedDiffOutputBuilder(
            "", // ヘッダーを空にする
            false // コンテキスト行を含めるかどうか(変更)
        ));
        return $differ->diff($this->old, $this->new);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.diff-display');
    }
}
