const expandableContent = (options) => ({
    expanded: false,
    showToggle: false,
    maxHeight: options.maxHeight || '6rem',
    expandedMaxHeight: options.expandedMaxHeight || '1000rem',
    _measured: false,
    _resizeObserver: null,

    init() {
        // 初回は x-intersect.once で activate() が呼ばれる。
        // ここでは以後のサイズ変化に備えて、必要最小限の再計測ハンドラだけを登録する。
        let resizeTimer = null;
        const resizeHandler = () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.remeasure();
            }, 150);
        };
        globalThis.addEventListener('resize', resizeHandler);
        if (typeof this.$cleanup === 'function') {
            this.$cleanup(() => {
                clearTimeout(resizeTimer);
                globalThis.removeEventListener('resize', resizeHandler);
                this.disconnectResizeObserver();
            });
        }
    },

    activate() {
        this.checkOverflow();
        this.startResizeObservation();
    },

    startResizeObservation() {
        if (this._resizeObserver || typeof globalThis.ResizeObserver === 'undefined') {
            return;
        }

        const content = this.$refs.content;
        if (!content) {
            return;
        }

        this._resizeObserver = new globalThis.ResizeObserver(() => {
            this.remeasure();
        });
        this._resizeObserver.observe(content);
    },

    disconnectResizeObserver() {
        if (!this._resizeObserver) {
            return;
        }

        this._resizeObserver.disconnect();
        this._resizeObserver = null;
    },

    remeasure() {
        this._measured = false;
        this.checkOverflow();
    },

    checkOverflow() {
        const content = this.$refs.content;
        if (!content) {
            return;
        }

        if (this.expanded) {
            this.showToggle = true;
            this._measured = true;
            return;
        }

        // 初回計測後は、remeasure() から呼ばれる場合のみ再判定を許可する。
        if (this._measured) {
            return;
        }

        const maxHeightInPixels = Number.parseFloat(globalThis.getComputedStyle(content).maxHeight);
        if (Number.isNaN(maxHeightInPixels)) {
            this.showToggle = false;
            this._measured = true;
            return;
        }

        this.showToggle = content.scrollHeight > maxHeightInPixels + 1;
        this._measured = true;
    },

    get contentStyle() {
        if (this.expanded) {
            return `max-height: ${this.expandedMaxHeight}; -webkit-mask-image: none; mask-image: none;`;
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

export default expandableContent;

