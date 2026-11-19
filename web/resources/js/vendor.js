import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';
import QRCode from 'qrcode';

window.bootstrap = bootstrap;
window.Swal = Swal;
window.ViteQRCode = QRCode;
if (typeof window.QRCode === 'undefined') {
    window.QRCode = QRCode;
}
