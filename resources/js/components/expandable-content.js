export default (options) => ({
    expanded: false,
    showToggle: false,
    maxHeight: options.maxHeight || '6rem',
    _measured: false,

    init() {
        // 初回計測はテンプレート側の x-intersect.once から明示的に呼び出し、
        // ここではリサイズ時の再計測のみを担当する。
        // リサイズ時は debounce して再計測
        let resizeTimer = null;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this._measured = false; // リサイズ後は再計測を許可
                this.checkOverflow();
            }, 150);
        });
    },

    checkOverflow() {
        const content = this.$refs.content;
        if (!content) return;

        // x-intersect から呼ばれた場合、既に計測済みなら showToggle を上書きしない
        if (this._measured) return;

        const maxHeightInPixels = parseFloat(window.getComputedStyle(content).maxHeight);
        const overflows = content.scrollHeight > maxHeightInPixels + 1;
        this.showToggle = overflows;
        this._measured = true;
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
