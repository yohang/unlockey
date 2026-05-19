import './bootstrap';

import './styles/app.scss';

if (typeof navigator.serviceWorker !== 'undefined') {
    navigator.serviceWorker.register('/sw.js');
}

require('bootstrap');
