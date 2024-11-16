console.log('ledgerDefineEdit.js loaded.');


import $ from 'jquery'
import select2 from 'select2';
import '@wotz/livewire-sortablejs';

//Hook up select2 to jQuery
select2(window, $);

window.jQuery = window.$ = $

// import 'livewire-sortable'


// let draggable = document.querySelector('[drag-root]');
// draggable.on('drag:start', () => console.log('drag:start'));
