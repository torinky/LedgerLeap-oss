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
                    const section = sections[0];
                    const json = section.getAttribute('data-confidentiality-json');
                    if (json) {
                        window.dispatchEvent(new CustomEvent('confidentiality-updated', {
                            detail: JSON.parse(json),
                        }));
                    }
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
                            const bestSection = Array.from(sections).find(
                                (s) => s.getAttribute('data-ledger-define-section') === bestId
                            );
                            if (bestSection) {
                                const json = bestSection.getAttribute('data-confidentiality-json');
                                if (json) {
                                    window.dispatchEvent(new CustomEvent('confidentiality-updated', {
                                        detail: JSON.parse(json),
                                    }));
                                }
                            }
                        }
                    },
                    {
                        root: null,
                        threshold: [0, 0.25, 0.5, 0.75, 1.0],
                    }
                );

                sections.forEach((section) => this.observer.observe(section));

                // Immediate initial selection in case observer doesn't fire synchronously
                if (sectionCount > 0 && Object.keys(this.lastRatio).length === 0) {
                    const firstVisible = Array.from(sections).find((s) => {
                        const rect = s.getBoundingClientRect();
                        return rect.top >= 0 && rect.bottom <= window.innerHeight;
                    }) || sections[0];
                    const json = firstVisible.getAttribute('data-confidentiality-json');
                    if (json) {
                        window.dispatchEvent(new CustomEvent('confidentiality-updated', {
                            detail: JSON.parse(json),
                        }));
                    }
                }
            } catch (e) {
                console.error('[confidentialityScrollTracker] error:', e);
            }
        });
    },
});
