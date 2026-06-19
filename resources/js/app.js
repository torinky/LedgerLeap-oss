import './bootstrap';

import '@fortawesome/fontawesome-free/css/all.min.css';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import intersect from '@alpinejs/intersect';

import expandableContent from './components/expandable-content.js';
import ledgerInitOverlay from './components/ledger-init-overlay.js';
import confidentialityScrollTracker from './components/confidentiality-scroll-tracker.js';
import ledgerSearchSuggest from './components/ledger-search-suggest.js';

globalThis.expandableContent ??= expandableContent;
globalThis.ledgerInitOverlay ??= ledgerInitOverlay;
globalThis.confidentialityScrollTracker ??= confidentialityScrollTracker;
globalThis.ledgerSearchSuggest ??= ledgerSearchSuggest;

const alpineRegistrations = globalThis.__ledgerLeapAlpineRegistrations ??= {
    expandableContent: false,
    ledgerInitOverlay: false,
    confidentialityScrollTracker: false,
    ledgerSearchSuggest: false,
    intersect: false,
};

const registerAlpineData = (alpine) => {
    if (!alpineRegistrations.expandableContent) {
        alpine.data('expandableContent', expandableContent);
        alpineRegistrations.expandableContent = true;
    }

    if (!alpineRegistrations.ledgerInitOverlay) {
        alpine.data('ledgerInitOverlay', ledgerInitOverlay);
        alpineRegistrations.ledgerInitOverlay = true;
    }

    if (!alpineRegistrations.confidentialityScrollTracker) {
        alpine.data('confidentialityScrollTracker', confidentialityScrollTracker);
        alpineRegistrations.confidentialityScrollTracker = true;
    }

    if (!alpineRegistrations.ledgerSearchSuggest) {
        alpine.data('ledgerSearchSuggest', ledgerSearchSuggest);
        alpineRegistrations.ledgerSearchSuggest = true;
    }
};

const bootAlpineIntegrations = () => {
    if (!alpineRegistrations.intersect) {
        Alpine.plugin(intersect);
        alpineRegistrations.intersect = true;
    }

    registerAlpineData(Alpine);

    if (!globalThis.Alpine) {
        globalThis.Alpine = Alpine;
    }
};

bootAlpineIntegrations();

Livewire.start();
