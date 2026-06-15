(() => {
    'use strict';

    // base.js references a global `modal` identifier from an older modal
    // implementation. Defining it keeps the Escape handler null-safe on V2.
    if (!Object.prototype.hasOwnProperty.call(window, 'modal')) {
        window.modal = null;
    }
})();
