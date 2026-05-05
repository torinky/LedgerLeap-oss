export default () => ({
    observer: null,
    lastRatio: {},
    lastSectionCount: null,

    init() {
        this.setupObserver();
    },

    destroy() {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
        this.lastRatio = {};
        this.lastSectionCount = null;
    },

    setupObserver() {
        if (! ('IntersectionObserver' in window)) {
            return;
        }

        this.$nextTick(() => {
            try {
                const sections = this.$el.querySelectorAll('[data-ledger-define-section]');
                const sectionCount = sections.length;

                // Skip if no sections or DOM structure hasn't changed
                if (sectionCount === 0) {
                    return;
                }
                if (sectionCount === this.lastSectionCount && this.observer) {
                    return;
                }

                // Tear down existing observer before creating a new one
                if (this.observer) {
                    this.observer.disconnect();
                    this.observer = null;
                }
                this.lastRatio = {};
                this.lastSectionCount = sectionCount;

                if (sectionCount === 1) {
                    const id = sections[0].getAttribute('data-ledger-define-section');
                    Livewire.dispatch('confidentialitySectionChanged', {
                        ledgerDefineId: parseInt(id, 10),
                    });
                    return;
                }

                this.observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            const id = entry.target.getAttribute('data-ledger-define-section');
                            this.lastRatio[id] = entry.intersectionRatio;
                        });

                        let bestId = null;
                        let bestRatio = 0;
                        for (const [id, ratio] of Object.entries(this.lastRatio)) {
                            if (ratio > bestRatio) {
                                bestRatio = ratio;
                                bestId = id;
                            }
                        }

                        if (bestId !== null) {
                            Livewire.dispatch('confidentialitySectionChanged', {
                                ledgerDefineId: parseInt(bestId, 10),
                            });
                        }
                    },
                    {
                        root: null,
                        threshold: [0, 0.25, 0.5, 0.75, 1.0],
                    }
                );

                sections.forEach((section) => this.observer.observe(section));
            } catch (e) {
                console.error('[confidentialityScrollTracker] error:', e);
            }
        });
    },
});
