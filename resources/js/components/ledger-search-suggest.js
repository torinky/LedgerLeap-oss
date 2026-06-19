const ledgerSearchSuggest = () => ({
    open: false,
    selectedIndex: -1,
    localSearch: '',
    searchUpdateTimer: null,
    lastCompositionEndAt: 0,
    compositionDepth: 0,
    commitDebounceMs: 250,

    dlog(event, payload) {
        if (!window.__ledgerSearchDebug) return;
        try {
            const ts = new Date().toISOString().slice(11, 23);
            console.log('[ledgerSearchSuggest ' + ts + '] ' + event, payload || {});
        } catch (e) {
            // console がない環境では無視
        }
    },

    get localEmpty() {
        return !this.localSearch;
    },
    get recentCount() {
        return this.$wire.recentSearches?.length || 0;
    },
    get relatedCount() {
        return this.localEmpty ? 0 : (this.$wire.querySuggestions?.length || 0);
    },
    get showRecent() {
        if (this.recentCount === 0) return false;
        if (this.localSearch !== '' && this.relatedCount >= 2) return false;
        return true;
    },
    get showPopularFallback() {
        return !this.localEmpty && this.relatedCount === 0 && (this.$wire.popularKeywords?.length || 0) > 0;
    },

    _dlogPopularDiagnostics() {
        if (this._dlogRunning) return;
        this._dlogRunning = true;
        try {
            this.dlog('popular diagnostics', {
                localSearch: this.localSearch,
                localEmpty: this.localEmpty,
                wireSearch: this.$wire.search,
                wirePopularKeywords_length: (this.$wire.popularKeywords || []).length,
                wirePopularKeywords_sample: (this.$wire.popularKeywords || []).slice(0, 3).map(p => p.keyword),
                showRecent: this.showRecent,
                showPopularFallback: this.showPopularFallback,
                recentCount: this.recentCount,
                relatedCount: this.relatedCount,
            });
        } finally {
            this._dlogRunning = false;
        }
    },

    _buildRecentSeen() {
        const seen = new Set();
        if (this.showRecent) {
            for (const r of (this.$wire.recentSearches || [])) {
                const key = (r.label || '').trim();
                if (key !== '') seen.add(key);
            }
        }
        return seen;
    },

    _buildRelatedSeen() {
        const seen = this._buildRecentSeen();
        for (const q of (this.$wire.querySuggestions || [])) {
            const key = (q.query_text || '').trim();
            if (key !== '') seen.add(key);
        }
        return seen;
    },

    get recentItems() {
        if (!this.showRecent) return [];
        const result = [];
        for (const r of (this.$wire.recentSearches || [])) {
            const key = (r.label || '').trim();
            if (key === '') continue;
            result.push({
                key: 'r-' + (r.id || result.length),
                label: r.label,
                section: 'recent',
                searchCount: r.result_count || 0,
                conditions: r.conditions,
            });
        }
        return result;
    },

    get relatedItems() {
        const seen = this._buildRecentSeen();
        const result = [];
        for (const q of (this.$wire.querySuggestions || [])) {
            const key = (q.query_text || '').trim();
            if (key === '' || seen.has(key)) continue;
            result.push({
                key: 'q-' + result.length,
                label: q.query_text,
                section: 'related',
                searchCount: q.search_count || 0,
                queryText: q.query_text,
            });
        }
        return result;
    },

    get popularItems() {
        if (this.localSearch !== '') return [];
        const seen = this._buildRelatedSeen();
        const result = [];
        for (const p of (this.$wire.popularKeywords || [])) {
            const key = (p.keyword || '').trim();
            if (key === '' || seen.has(key)) continue;
            result.push({
                key: 'p-' + result.length,
                label: p.keyword,
                section: 'popular',
                searchCount: p.search_count || 0,
                keyword: p.keyword,
            });
        }
        return result;
    },

    get totalItems() {
        return this.recentItems.length + this.relatedItems.length + this.popularItems.length;
    },

    getUnifiedSuggestionsForNav() {
        const result = [];
        for (const r of (this.recentItems || [])) result.push(r);
        for (const q of (this.relatedItems || [])) result.push(q);
        for (const p of (this.popularItems || [])) result.push(p);
        return result;
    },

    get hasItems() {
        return this.totalItems > 0;
    },

    get popularStartIndex() {
        let start = 0;
        if (this.showRecent) start += this.recentCount;
        start += this.relatedCount;
        return start;
    },

    get inputTokens() {
        return (this.localSearch || '').split(/[\s\u3000]+/).filter(t => t.length > 0);
    },

    highlight(text) {
        if (!text || this.inputTokens.length === 0) {
            return this.escapeHtml(text || '');
        }
        const escaped = this.escapeHtml(text);
        const pattern = this.inputTokens
            .map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
            .filter(t => t.length > 0)
            .join('|');
        if (!pattern) return escaped;
        const re = new RegExp('(' + pattern + ')', 'g');
        return escaped.replace(re, '<strong class="font-bold text-primary">$1</strong>');
    },

    escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    navigateDown() {
        if (this.totalItems > 0) {
            this.selectedIndex = (this.selectedIndex + 1) % this.totalItems;
        }
    },

    navigateUp() {
        if (this.totalItems > 0) {
            this.selectedIndex = this.selectedIndex <= 0 ? this.totalItems - 1 : this.selectedIndex - 1;
        }
    },

    selectCurrent() {
        if (this.selectedIndex < 0) return;
        event.preventDefault();
        const items = this.getUnifiedSuggestionsForNav();
        const item = items[this.selectedIndex];
        if (!item) return;
        this.selectUnified(item);
        this.open = false;
        this.selectedIndex = -1;
    },

    selectUnified(item) {
        if (!item) return;
        if (item.section === 'recent') {
            this.$wire.applySearch(item.conditions);
        } else if (item.section === 'related') {
            this.$wire.applyQuerySuggestion(item.queryText);
        } else if (item.section === 'popular') {
            this.$wire.applyKeywordSearch(item.keyword);
        }
    },

    onEnterKey(e) {
        const keyEvent = e.key || '';
        const isIMEEvent = e.isComposing
            || e.keyCode === 229
            || this.isComposing
            || this.compositionDepth > 0;
        const recentComposition = Date.now() - this.lastCompositionEndAt < 100;
        if (isIMEEvent) {
            return;
        }
        if (recentComposition) {
            return;
        }
        if (this.open && this.hasItems && this.selectedIndex >= 0) {
            console.log('[ledgerSearchSuggest] onEnterKey → selectCurrent', { selectedIndex: this.selectedIndex });
            this.selectCurrent();
        } else {
            console.log('[ledgerSearchSuggest] onEnterKey → sendSearchRequest', { open: this.open, hasItems: !!this.hasItems, selectedIndex: this.selectedIndex });
            this.sendSearchRequest();
        }
    },

    onSpaceKey(e) {
        const isIMEEvent = e.isComposing
            || e.keyCode === 229
            || this.isComposing
            || this.compositionDepth > 0;
        if (isIMEEvent) {
            this.dlog('onSpaceKey skip (IME)', { depth: this.compositionDepth, keyCode: e.keyCode });
            return;
        }
        this.dlog('onSpaceKey commit', {});
        setTimeout(() => {
            this.scheduleSuggestions();
        }, 0);
    },

    syncLocalFromInput() {
        this.localSearch = this.$wire.search || '';
    },

    onLocalInput() {
        this.open = true;
        if (this.isComposing || this.compositionDepth > 0) {
            this.dlog('onLocalInput skipped (IME composing)', { depth: this.compositionDepth });
            return;
        }
        this.dlog('onLocalInput', { localSearch: this.localSearch });
        this.scheduleSuggestions();
    },

    scheduleSuggestions() {
        if (this.searchUpdateTimer) {
            clearTimeout(this.searchUpdateTimer);
        }
        this.searchUpdateTimer = setTimeout(() => {
            this.commitSuggestions();
        }, this.commitDebounceMs);
    },

    commitSuggestions() {
        if (this.searchUpdateTimer) {
            clearTimeout(this.searchUpdateTimer);
            this.searchUpdateTimer = null;
        }
        const newValue = String(this.localSearch == null ? '' : this.localSearch);
        this.dlog('commitSuggestions', { local: newValue });
        this.$wire.updateSuggestions(newValue);
    },

    sendSearchRequest() {
        const newValue = String(this.localSearch == null ? '' : this.localSearch);
        console.log('[ledgerSearchSuggest] sendSearchRequest', { value: newValue, wire: !!this.$wire, executeSearch: typeof this.$wire.executeSearch });
        this.open = false;
        this.selectedIndex = -1;
        this.$wire.executeSearch(newValue);
    },

    init() {
        console.log('[ledgerSearchSuggest] init', { search: this.$wire.search });

        this.isComposing = false;
        this.localSearch = this.$wire.search || '';

        // Sync localSearch when server commits search (Enter, applySearch, applyKeywordSearch, etc.)
        this.$watch('$wire.search', val => {
            this.localSearch = val || '';
        });

        // Window-level Escape handler
        this._escHandler = (e) => {
            if (e.key !== 'Escape') return;
            console.log('[ledgerSearchSuggest] Escape', { open: this.open, localSearch: this.localSearch });
            if (this.open) {
                // 1回目: パネル閉じるのみ (サーバ更新は不要)
                this.open = false;
                this.selectedIndex = -1;
            } else if (this.localSearch !== '') {
                // 2回目: 入力クリア
                this.localSearch = '';
                this.$wire.clearSearch();
            }
        };
        window.addEventListener('keydown', this._escHandler);

        this.$nextTick(() => {
            const input = this.$root.querySelector('input[type="search"]');
            console.log('[ledgerSearchSuggest] input found:', !!input, input?.type);
            if (!input) return;

            input.addEventListener('compositionstart', () => {
                this.compositionDepth += 1;
                this.isComposing = true;
            });

            input.addEventListener('compositionend', (e) => {
                this.compositionDepth = Math.max(0, this.compositionDepth - 1);
                if (this.compositionDepth === 0) {
                    this.isComposing = false;
                }
                this.lastCompositionEndAt = Date.now();
                requestAnimationFrame(() => {
                    this.localSearch = input.value || '';
                });
            });

            // Explicit Enter handler on the native input
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    console.log('[ledgerSearchSuggest] Enter keydown', { composing: e.isComposing, depth: this.compositionDepth, localSearch: this.localSearch });
                    e.preventDefault();
                    this.onEnterKey(e);
                }
            });
        });
    },
});

export default ledgerSearchSuggest;
