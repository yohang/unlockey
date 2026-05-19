import {Html5Qrcode} from "html5-qrcode";
import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['lockerSelect', 'qrCodeButton', 'qrDialog', 'qrCloseButton'];

    connect() {
        const select = this.targets.find('lockerSelect') as HTMLSelectElement;

        select.addEventListener('change', (event) => {
            [].forEach.call((event.target as HTMLSelectElement).selectedOptions, (value) => {
                const url = (value as HTMLOptionElement).value;

                if (url) {
                    window.location.assign(url);
                }
            });
        });

        const qrCodeButton = this.targets.find('qrCodeButton') as HTMLButtonElement;
        const qrCloseButton = this.targets.find('qrCloseButton') as HTMLButtonElement;
        const qrDialog = this.targets.find('qrDialog') as HTMLDialogElement;
        qrCloseButton.addEventListener('click', () => qrDialog.close());
        qrCodeButton.addEventListener('click', () => {
            qrDialog.showModal();

            Html5Qrcode.getCameras()
                .then(devices => {
                    if (devices && devices.length) {
                        const html5QrCode = new Html5Qrcode('qr-reader');
                        html5QrCode.start(
                            devices[0].id,
                            {fps: 10, qrbox: Math.round(window.innerWidth * 0.5), videoConstraints: {facingMode: 'environment'}},
                            (decodedText) => {
                                const url = decodedText.match(/(\/app\/locker\/[-\w]+)$/i);
                                if (!(url && url[0])) {
                                    return;
                                }

                                window.location.assign(url[0]);
                            },
                            () => {},
                        );
                    }
                });
        });
    }
}
