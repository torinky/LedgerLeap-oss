import './bootstrap';

// import '@fortawesome/fontawesome-free/js/solid.min.js';
// import '@fortawesome/fontawesome-free/js/fontawesome.js';
import '@fortawesome/fontawesome-free/js/all'

import expandableContent from './components/expandable-content.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('expandableContent', expandableContent)
});
