console.log('ledgerDefineEdit.js loaded.');

// require('livewire-sortable');
// import 'livewire-sortable';

// const $ = require("jquery");
import jQuery from "jquery";
// require('select2');
import select2 from 'select2';

window.$ = jQuery;

select2();
// require('./app.js')


$(document).ready(function () {
    $('.js-attachSelect2Tag').select2({
        tags: true
    });
});
// let draggable = document.querySelector('[drag-root]');
// draggable.on('drag:start', () => console.log('drag:start'));
