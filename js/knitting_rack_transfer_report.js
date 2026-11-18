function printTransferRow(button) {
    var row;

    try {
        row = JSON.parse(button.getAttribute('data-print-row') || '{}');
    } catch (error) {
        console.error('JSON Error:', error);
        alert('Unable to prepare this transfer record.');
        return;
    }

    if (!row || !row.ROLL) {
        alert('Roll number is missing.');
        return;
    }

    var printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert('Please allow pop-ups for this website to print.');
        return;
    }

    try {
        var qrDataUrl = '';

        if (typeof QRCode !== 'undefined') {
            var qrHolder = document.createElement('div');
            new QRCode(qrHolder, {
                text: String(row.ROLL),
                width: 116,
                height: 116,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });

            var qrCanvas = qrHolder.querySelector('canvas');
            var qrImage = qrHolder.querySelector('img');

            if (qrCanvas) {
                qrDataUrl = qrCanvas.toDataURL('image/png');
            } else if (qrImage && qrImage.src) {
                qrDataUrl = qrImage.src;
            }
        }

        var printHTML = buildPrintDocument(row, qrDataUrl);
        printWindow.document.open();
        printWindow.document.write(printHTML);
        printWindow.document.close();

        printWindow.onafterprint = function() {
            printWindow.close();
        };

        setTimeout(function() {
            if (!printWindow.closed) {
                printWindow.focus();
                printWindow.print();
            }
        }, 600);
    } catch (error) {
        console.error('Print Error:', error);
        alert('Unable to print this transfer record.');

        if (!printWindow.closed) {
            printWindow.close();
        }
    }
}
