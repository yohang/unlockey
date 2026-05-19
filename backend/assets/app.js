import './bootstrap.js';
import './styles/app.css';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';

if (typeof navigator.serviceWorker !== 'undefined') {
    navigator.serviceWorker.register('/sw.js');
}
