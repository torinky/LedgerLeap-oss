export default (options) => ({
    expanded: false,
    showToggle: false,
    maxHeight: options.maxHeight || '6rem',

    init() {
        // $el (コンポーネントのルート要素) がDOMに追加された後に実行
        this.$nextTick(() => {
            this.checkOverflow();
        });

        // リサイズイベントで再チェック
        // パフォーマンスのためにdebounceをかけるのが望ましいが、まずはシンプルに実装
        window.addEventListener('resize', () => this.checkOverflow());
    },

    checkOverflow() {
        const content = this.$refs.content;
        if (!content) return;

        // スタイルが適用されるのを待つ
        this.$nextTick(() => {
            const maxHeightInPixels = parseFloat(window.getComputedStyle(content).maxHeight);
            // わずかな誤差を許容するために +1 する
            this.showToggle = content.scrollHeight > maxHeightInPixels + 1;
        });
    },

    get contentStyle() {
        if (this.expanded) {
            const content = this.$refs.content;
            return `max-height: ${content ? content.scrollHeight : 2000}px`;
        }

        const baseStyle = `max-height: ${this.maxHeight}`;

        if (this.showToggle) {
            // コンテンツ自体にマスクをかける
            return `${baseStyle}; -webkit-mask-image: linear-gradient(to bottom, black calc(100% - 3rem), transparent 100%); mask-image: linear-gradient(to bottom, black calc(100% - 3rem), transparent 100%);`;
        }

        return baseStyle;
    },

    toggle() {
        this.expanded = !this.expanded;
    }
});
