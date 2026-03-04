/**
 * 台帳リスト初期化中オーバーレイ Alpine.js コンポーネント
 *
 * app.js の alpine:init 内で Alpine.data('ledgerInitOverlay', ledgerInitOverlay) として登録される。
 * Bladeビューでは x-data="ledgerInitOverlay" で参照する。
 *
 * 動作:
 * - ページロード直後からオーバーレイを表示（visible: true）
 * - livewire:navigated イベント受信でオーバーレイを非表示（Alpine.js の x-show でフェードアウト）
 * - フォールバック: 8秒後に強制非表示
 *
 * タイミング計測:
 * - t0 はコンポーネント初期化時刻（performance.now()）
 * - hide() 呼び出し時に経過時間をコンソールに出力
 *
 * 設計上の注意:
 * - Blade テンプレート内で x-data に直接 JS オブジェクトリテラル（メソッドショートハンド等）を
 *   書くと PHP クロージャとして誤解釈されるため、Alpine.data() で外部定義する方式を採用。
 * - @livewire:navigated.window.once でイベントを一度だけ受け取る。
 */
export default function ledgerInitOverlay() {
    return {
        visible: true,
        t0: performance.now(),

        hide() {
            if (!this.visible) return;
            const elapsed = (performance.now() - this.t0).toFixed(0);
            console.log('[INIT-TIMING] overlay hidden at ' + elapsed + 'ms after page load');
            this.visible = false;
        },

        startFallbackTimer() {
            const self = this;
            setTimeout(function () {
                if (self.visible) {
                    console.log('[INIT-TIMING] overlay hidden by 8s fallback timeout');
                    self.hide();
                }
            }, 8000);
        },
    };
}

