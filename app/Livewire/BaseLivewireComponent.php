<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * すべてのLivewireコンポーネントの基底クラス
 *
 * Livewire 3でのtoJSON()メソッド呼び出しエラーを回避するための共通メソッドを提供します。
 */
abstract class BaseLivewireComponent extends Component
{
    /**
     * フロントエンドでのシリアライズ（JSON化）時に呼ばれる可能性がある空メソッド
     * Livewire 3 での MethodNotFoundException 回避用
     */
    public function toJSON(): void
    {
        // 意図的に空にしています
    }
}
