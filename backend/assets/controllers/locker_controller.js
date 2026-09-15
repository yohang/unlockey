import { Controller } from '@hotwired/stimulus';

const STATE_LABELS = { open: 'Ouvert', closed: 'Fermé' };
const TRANSITION_LABELS = { open: 'Ouvrir', close: 'Fermer' };

/**
 * Drives a locker through the API: transitions are POSTed by XHR and the page is
 * refreshed from the JSON-LD response, or from the Mercure updates of the locker.
 */
export default class extends Controller {
    static values = { url: String, mercureUrl: String };
    static targets = ['state', 'actions', 'error'];

    connect() {
        this.source = new EventSource(this.mercureUrlValue, { withCredentials: true });
        this.source.onmessage = (event) => this.render(JSON.parse(event.data));
    }

    disconnect() {
        this.source?.close();
    }

    async apply(event) {
        const transition = event.currentTarget.dataset.transition;
        this.hideError();
        this.actionsTarget.querySelectorAll('button').forEach((button) => { button.disabled = true; });

        try {
            const response = await this.request(`${this.urlValue}/${transition}`, 'POST');

            if (response.status === 409) {
                this.showError("Le casier a déjà changé d'état.");
                this.render(await (await this.request(this.urlValue)).json());
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            this.render(await response.json());
        } catch (error) {
            this.showError('Une erreur est survenue, veuillez réessayer.');
            this.actionsTarget.querySelectorAll('button').forEach((button) => { button.disabled = false; });
        }
    }

    request(url, method = 'GET') {
        return fetch(url, { method, headers: { Accept: 'application/ld+json' } });
    }

    render(locker) {
        this.stateTarget.textContent = STATE_LABELS[locker.state] ?? locker.state;
        this.actionsTarget.replaceChildren(...(locker.transitions ?? []).map((transition) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-lg btn-primary';
            button.dataset.action = 'locker#apply';
            button.dataset.transition = transition;
            button.textContent = TRANSITION_LABELS[transition] ?? transition;

            return button;
        }));
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('d-none');
    }

    hideError() {
        this.errorTarget.classList.add('d-none');
    }
}
