import './bootstrap';

import '@fortawesome/fontawesome-free/css/all.min.css';

import expandableContent from './components/expandable-content.js';
import ledgerInitOverlay from './components/ledger-init-overlay.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('expandableContent', expandableContent)
    Alpine.data('ledgerInitOverlay', ledgerInitOverlay)
});
