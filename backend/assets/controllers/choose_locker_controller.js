import { Controller } from '@hotwired/stimulus';
import { Html5Qrcode } from 'html5-qrcode';

export default class extends Controller {
    static targets = ['lockerSelect', 'qrCodeButton', 'qrDialog', 'qrCloseButton'];

    connect() {
        this.handleLockerChange = this.handleLockerChange.bind(this);
        this.handleQrCodeButtonClick = this.handleQrCodeButtonClick.bind(this);
        this.handleQrCloseButtonClick = this.handleQrCloseButtonClick.bind(this);

        this.lockerSelectTarget.addEventListener('change', this.handleLockerChange);
        this.qrCodeButtonTarget.addEventListener('click', this.handleQrCodeButtonClick);
        this.qrCloseButtonTarget.addEventListener('click', this.handleQrCloseButtonClick);
    }

    disconnect() {
        this.lockerSelectTarget.removeEventListener('change', this.handleLockerChange);
        this.qrCodeButtonTarget.removeEventListener('click', this.handleQrCodeButtonClick);
        this.qrCloseButtonTarget.removeEventListener('click', this.handleQrCloseButtonClick);
    }

    handleLockerChange(event) {
        for (const option of event.target.selectedOptions) {
            if (option.value) {
                window.location.assign(option.value);
            }
        }
    }

    handleQrCloseButtonClick() {
        this.qrDialogTarget.close();
    }

    handleQrCodeButtonClick() {
        this.qrDialogTarget.showModal();

        Html5Qrcode.getCameras().then((devices) => {
            if (!devices.length) {
                return;
            }

            const html5QrCode = new Html5Qrcode('qr-reader');

            html5QrCode.start(
                devices[0].id,
                {
                    fps: 10,
                    qrbox: Math.round(window.innerWidth * 0.5),
                    videoConstraints: { facingMode: 'environment' },
                },
                (decodedText) => {
                    const url = decodedText.match(/(\/app\/locker\/[-\w]+)$/i);
                    if (!(url && url[0])) {
                        return;
                    }

                    window.location.assign(url[0]);
                },
                () => {},
            );
        });
    }
}
