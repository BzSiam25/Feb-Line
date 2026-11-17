<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knitting | Batch Delivery</title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 18px;
            background: linear-gradient(135deg, #d9f1ed, #e5f1fb);
            color: #123b39;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .page { width: 100%; margin: 0 auto; }
        .header, .panel {
            background: #fff;
            border: 1px solid #c9dedb;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(15, 70, 65, .1);
        }
        .header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            margin-bottom: 14px;
            background: linear-gradient(135deg, #0f766e 0%, #0d8a80 50%, #0f9b8e 100%);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .1);
            color: #fff;
        }
        .header h1 {
            grid-column: 2;
            grid-row: 1;
            text-align: center;
            font-size: clamp(1.2rem, 3vw, 1.5rem);
        }
        h1 { margin: 0; font-size: 1.5rem; }
        .back {
            display: inline-block;
            justify-self: start;
            padding: 9px 14px;
            color: #fff;
            background: #000;
            border: 2px solid #000;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            white-space: nowrap;
            transition: 0.2s ease;
        }
        .back:hover { color: #000; background: #fff; border-color: #000; }
        .panel { padding: 18px; margin-bottom: 14px; }
        .panel-title { margin: 0 0 14px; font-size: 1.08rem; font-weight: 800; }
        .batch-form, .scan-form { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; }
        .field { flex: 1; min-width: 220px; }
        .roll-input-group { display: flex; width: 100%; }
        .roll-input-group input { flex: 1; min-width: 0; border-radius: 8px 0 0 8px; }
        .roll-input-group button {
            flex: 0 0 auto;
            border-radius: 0 8px 8px 0;
        }
        label { display: block; margin-bottom: 6px; font-weight: 700; }
        input {
            width: 100%;
            min-height: 46px;
            padding: 10px 12px;
            border: 1px solid #afc8c4;
            border-radius: 8px;
            font-size: 1rem;
        }
        input:focus { outline: 2px solid #54b7a8; border-color: #159685; }
        button {
            min-height: 44px;
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            color: #fff;
            background: #087f70;
            font-weight: 800;
            font-size: .98rem;
            cursor: pointer;
        }
        button:hover { filter: brightness(.94); }
        button:disabled { background: #94a3b8; cursor: not-allowed; }
        .scan-button { background: #2563eb; }
        .add-roll-button { flex: 0 0 auto; }
        .confirm-button { background: #059669; }
        .message { display: none; padding: 11px 13px; margin-top: 12px; border-radius: 8px; font-weight: 700; }
        .message.info { display: block; color: #075985; background: #e0f2fe; }
        .message.error { display: block; color: #991b1b; background: #fee2e2; }
        .message.success { display: block; color: #065f46; background: #d1fae5; }
        .is-hidden { display: none !important; }
        .batch-summary { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 14px; }
        .summary-item { padding: 9px 12px; border-radius: 8px; background: #ecfdf5; font-weight: 800; }
        .camera-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; justify-items: center; }
        .scanner-stage { width: min(100%, 460px); }
        #qr-reader { width: 100%; min-height: 300px; overflow: hidden; border-radius: 12px; background: #f1f5f9; }
        .scanner-controls { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 10px; }
        .manual-scan { width: min(100%, 1100px); }
        .scan-help { margin: 0 0 12px; color: #475569; }
        .table-wrap { width: 100%; overflow: auto; max-height: calc(100vh - 350px); }
        table { width: 100%; min-width: 1900px; border-collapse: collapse; font-size: .92rem; }
        thead th {
            position: sticky;
            top: 0;
            padding: 11px 9px;
            color: #fff;
            background: #134e4a;
            text-align: left;
            white-space: nowrap;
            z-index: 1;
        }
        tbody td { padding: 10px 9px; border-bottom: 1px solid #dbe5e4; white-space: nowrap; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        td.batch-card-cell, td.process-cell { vertical-align: middle; text-align: center; }
        .process-badges {
            display: grid;
            grid-template-columns: repeat(2, max-content);
            justify-content: center;
            gap: 5px;
            min-width: 120px;
        }
        .process-badge {
            display: inline-block;
            padding: 4px 8px;
            border: 1px solid #99d5c9;
            border-radius: 6px;
            color: #075e54;
            background: #e6f8f3;
            font-size: .78rem;
            font-weight: 750;
        }
        tfoot th { padding: 12px 9px; background: #ecfdf5; color: #065f46; text-align: right; white-space: nowrap; }
        .actions { display: flex; justify-content: flex-start; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
        .another-batch-button { background: #334155; }
        .roll-badge { display: inline-block; padding: 4px 9px; border-radius: 20px; color: #065f46; background: #ccfbf1; font-weight: 800; }
        .remove-roll-button {
            min-height: 32px;
            padding: 5px 9px;
            background: #fee2e2;
            color: #b91c1c;
        }
        .remove-batch-button { min-height: 32px; padding: 5px 9px; background: #fee2e2; color: #b91c1c; }
        .challan-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
        .challan-fields .field { min-width: 0; }
        .challan-fields .field-to { grid-column: 1 / -1; }
        .remark-input { min-width: 180px; min-height: 36px; padding: 6px 9px; }
        #scannedPanel table { min-width: 900px; }
        .print-button { background: #2563eb; }
        #challanPrint { display: none; }
        .challan-paper { color: #111; font: 12px Arial, sans-serif; }
        .challan-paper-header { display: grid; grid-template-columns: 1fr 3fr 1fr; align-items: start; gap: 10px; }
        .challan-brand { font-size: 15px; font-style: italic; font-weight: 700; }
        .challan-company { text-align: center; }
        .challan-company h1 { margin: 0; font-size: 19px; }
        .challan-company p { margin: 3px 0; font-size: 10px; }
        .challan-title { display: inline-block; margin: 8px 0 10px; padding: 2px 14px; border: 1px solid #111; border-radius: 20px; font-size: 14px; font-weight: 700; }
        .challan-meta { display: grid; grid-template-columns: 1fr 1fr; column-gap: 22px; row-gap: 6px; margin: 8px 0 14px; }
        .challan-meta div { min-height: 20px; }
        .challan-meta .challan-to { grid-column: 1 / -1; }
        .challan-paper table { width: 100%; min-width: 0; border-collapse: collapse; font-size: 11px; }
        .challan-paper th, .challan-paper td { padding: 6px; border: 1px solid #222; text-align: center; vertical-align: middle; }
        .challan-paper th { text-align: center; }
        .challan-paper tfoot th { background: #fff; color: #111; }
        .challan-notes { margin-top: 12px; }
        .challan-note-line { min-height: 24px; margin-bottom: 5px; }
        .challan-signatures { display: flex; justify-content: space-between; gap: 12px; margin-top: 48px; }
        .challan-signatures span { min-width: 90px; padding-top: 5px; border-top: 1px solid #222; text-align: center; }
        @media (max-width: 800px) {
            body { padding: 16px 9px 9px; }
            .header { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 8px; padding: 10px; }
            .header h1 { font-size: 1.1rem; }
            .back { padding: 8px 10px; }
            #qr-reader { min-height: 260px; }
            .table-wrap { max-height: 55vh; }
            .challan-fields { grid-template-columns: 1fr; }
        }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { margin: 0; padding: 0; background: #fff; }
            body > * { display: none !important; }
            #challanPrint { display: block !important; width: 100%; color: #111; background: #fff; }
            .challan-paper { width: 100%; }
            .challan-paper table { page-break-inside: auto; }
            .challan-paper thead { display: table-header-group; }
            .challan-paper tr { page-break-inside: avoid; }
            .challan-paper tfoot { display: table-row-group; }
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="header">
            <a class="back" href="initialPage.php">← Back</a>
            <h1>Knitting Batch Delivery</h1>
        </header>

        <section class="panel" id="deliveryPanel">
            <h2 class="panel-title">Scan the rolls you want to deliver; use Add Another Batch to include more batches.</h2>
            <div class="message" id="message" role="status" aria-live="polite"></div>
            <div class="batch-summary">
                <div class="summary-item">Batch: <span id="summaryCardNo">-</span></div>
                <div class="summary-item">Rolls scanned: <span id="scannedCount">0</span> / <span id="expectedCount">0</span></div>
                <div class="summary-item">Scanned QTY: <span id="scannedQty">0.000</span> KG</div>
                <div class="summary-item">Batch Total QTY: <span id="expectedQty">0.000</span> KG</div>
            </div>
            <div class="camera-layout">
                <div class="scanner-stage">
                    <div id="qr-reader"></div>
                    <div class="scanner-controls">
                        <button class="scan-button" id="cameraBtn" type="button">Start Camera Scanner</button>
                        <button type="button" id="switchCameraBtn">Switch to Front Camera</button>
                    </div>
                </div>
                <div class="manual-scan">
                    <form class="scan-form" id="rollForm">
                        <div class="field">
                            <label for="rollInput">Roll Number</label>
                            <div class="roll-input-group">
                                <input type="text" id="rollInput" autocomplete="off" placeholder="Scan or enter roll number">
                                <button type="submit" id="addRollBtn">Add</button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="remainingQtyInput">Remaining QTY (KG)</label>
                            <input type="text" id="remainingQtyInput" value="-" readonly>
                        </div>
                        <div class="field">
                            <label for="requestQtyInput">Request QTY (KG)</label>
                            <input type="number" id="requestQtyInput" min="0" step="0.001" value="0" placeholder="Extra quantity requested">
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="panel is-hidden" id="scannedPanel">
            <h2 class="panel-title">Scanned Rolls</h2>
            <div class="challan-fields">
                <div class="field field-to">
                    <label for="toNameInput">To</label>
                    <input type="text" id="toNameInput" maxlength="255" autocomplete="off">
                </div>
                <div class="field">
                    <label for="gatePassNoInput">Gate Pass No.</label>
                    <input type="text" id="gatePassNoInput" maxlength="100" autocomplete="off">
                </div>
                <div class="field">
                    <label for="vehicleNoInput">Vehicle No.</label>
                    <input type="text" id="vehicleNoInput" maxlength="100" autocomplete="off">
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Action</th><th>Batch</th><th>Roll</th><th>Fabrics Type</th><th>Qnty.</th><th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="scannedRows">
                        <tr><td colspan="6">Scan rolls after loading a batch card.</td></tr>
                    </tbody>
                    <tfoot id="scannedFooter"></tfoot>
                </table>
            </div>
            <div class="actions">
                <button class="confirm-button" id="confirmBtn" type="button" disabled>Confirm Delivery Challan</button>
                <button class="another-batch-button" id="anotherBatchBtn" type="button" hidden>Add Another Batch</button>
                <button class="scan-button" id="printChallanBtn" type="button" hidden>Print Delivery Challan</button>
            </div>
        </section>
    </main>
    <section id="challanPrint" aria-hidden="true"></section>

    <script>
        (function() {
            'use strict';

            var deliveryPanel = document.getElementById('deliveryPanel');
            var scannedPanel = document.getElementById('scannedPanel');
            var rollForm = document.getElementById('rollForm');
            var rollInput = document.getElementById('rollInput');
            var remainingQtyInput = document.getElementById('remainingQtyInput');
            var confirmBtn = document.getElementById('confirmBtn');
            var cameraBtn = document.getElementById('cameraBtn');
            var switchCameraBtn = document.getElementById('switchCameraBtn');
            var anotherBatchBtn = document.getElementById('anotherBatchBtn');
            var message = document.getElementById('message');
            var qrScanner = null;
            var batch = null;
            var scannedRolls = new Map();
            var expectedByRoll = new Map();
            var alreadyDeliveredRolls = new Set();
            var completedBatches = [];
            var remarksByBatch = new Map();
            var processingRoll = false;
            var scanPopupActive = false;
            var delivered = false;
            var batchAlreadyDelivered = false;
            var alreadyDeliveredMessage = '';
            var cameraFacingMode = 'environment';
            var cameraSwitching = false;
            var resumeCameraAfterRollRemoval = false;

            function showMessage(text, type) {
                message.textContent = text;
                message.className = 'message ' + (type || 'info');
            }

            function showScanPopup(text, title, icon, duration) {
                message.textContent = '';
                message.className = 'message';
                scanPopupActive = true;
                rollInput.disabled = true;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: icon || 'warning',
                        title: title || 'Quantity / Roll Mismatch',
                        text: text,
                        timer: duration || 3000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(function() {
                        scanPopupActive = false;
                        rollInput.disabled = delivered;
                        if (!delivered) rollInput.focus();
                    });
                    return;
                }

                showMessage(text, icon === 'success' ? 'success' : 'error');
                window.setTimeout(function() {
                    message.textContent = '';
                    message.className = 'message';
                    scanPopupActive = false;
                    rollInput.disabled = delivered;
                    if (!delivered) rollInput.focus();
                }, duration || 3000);
            }

            function escapeHtml(value) {
                var element = document.createElement('span');
                element.textContent = value === null || value === undefined ? '' : String(value);
                return element.innerHTML;
            }

            function formatQty(value) {
                var qty = Number(value);
                return Number.isFinite(qty) ? qty.toFixed(3) : '0.000';
            }

            function extractRollValue(value) {
                var text = String(value || '').trim();
                if (!text) return '';

                if (text.indexOf('|') !== -1) {
                    text = text.split('|')[0].trim();
                }

                var match = text.match(/ROLL\s*:\s*([^\r\n]+)/i);
                if (match && match[1]) return match[1].trim();

                return text;
            }

            function api(action, payload, queryParams) {
                var options = payload
                    ? {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)}
                    : {method: 'GET'};
                var query = new URLSearchParams(queryParams || {});
                query.set('action', action);
                return fetch('ajaxKnitting_batch_delivery.php?' + query.toString(), options)
                    .then(function(response) {
                        return response.json().then(function(data) {
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Request failed.');
                            }
                            return data;
                        });
                    });
            }

            function getDeliveryGroups() {
                var groups = completedBatches.map(function(savedBatch) {
                    return {
                        card_no: savedBatch.card_no,
                        rows: savedBatch.rows,
                        target_qty: Number(savedBatch.target_qty) || 0,
                        expected_count: Number(savedBatch.expected_count) || savedBatch.rows.length,
                        remarks: remarksByBatch.has(String(savedBatch.card_no))
                            ? remarksByBatch.get(String(savedBatch.card_no))
                            : (savedBatch.remarks || ''),
                        completed: true
                    };
                });
                if (batch && scannedRolls.size) {
                    groups.push({
                        card_no: batch.card_no,
                        rows: Array.from(scannedRolls.values()),
                        target_qty: Number(batch.target_qty) || 0,
                        expected_count: batch.rows.length,
                        remarks: remarksByBatch.get(String(batch.card_no)) || '',
                        completed: false
                    });
                }
                return groups;
            }

            function getCurrentBatchRolls() {
                return batch ? batch.rows.length : 0;
            }

            function getAlreadyDeliveredRollCount() {
                var count = 0;
                alreadyDeliveredRolls.forEach(function(roll) {
                    if (expectedByRoll.has(String(roll))) count++;
                });
                return count;
            }

            function getAlreadyDeliveredQty() {
                var total = 0;
                alreadyDeliveredRolls.forEach(function(roll) {
                    var row = expectedByRoll.get(String(roll));
                    if (row) total += Number(row.QTY) || 0;
                });
                return total;
            }

            function isCurrentBatchComplete() {
                return batch
                    ? scannedRolls.size + getAlreadyDeliveredRollCount() === batch.rows.length
                    : completedBatches.length > 0;
            }

            function updateSummary() {
                var groups = getDeliveryGroups();
                var scannedCount = 0;
                var expectedCount = completedBatches.reduce(function(total, savedBatch) {
                    return total + (Number(savedBatch.expected_count) || savedBatch.rows.length);
                }, 0) + getCurrentBatchRolls();
                var totalQty = 0;
                var targetQty = completedBatches.reduce(function(total, savedBatch) {
                    return total + (Number(savedBatch.target_qty) || 0);
                }, 0) + (batch ? Number(batch.target_qty) || 0 : 0);
                groups.forEach(function(group) {
                    group.rows.forEach(function(row) {
                        scannedCount++;
                        totalQty += Number(row.QTY) || 0;
                    });
                });

                document.getElementById('summaryCardNo').textContent = batch
                    ? batch.card_no
                    : (completedBatches.length ? completedBatches.length + ' batch(es) ready' : '-');
                document.getElementById('scannedCount').textContent = String(scannedCount);
                document.getElementById('expectedCount').textContent = String(expectedCount);
                document.getElementById('scannedQty').textContent = formatQty(totalQty);
                document.getElementById('expectedQty').textContent = formatQty(targetQty);
                remainingQtyInput.value = batch
                    ? formatQty(Math.max(0, Number(batch.target_qty) - getAlreadyDeliveredQty() -
                        Array.from(scannedRolls.values()).reduce(function(total, row) {
                            return total + (Number(row.QTY) || 0);
                        }, 0)))
                    : '-';
                document.getElementById('scannedFooter').innerHTML = groups.length
                    ? '<tr><th colspan="4">Total Qnty.</th><th>' + formatQty(totalQty) + ' KG</th><th></th></tr>'
                    : '';

                var batchComplete = isCurrentBatchComplete();
                deliveryPanel.classList.toggle('is-hidden', Boolean(batch && batchComplete) || delivered);
                scannedPanel.classList.toggle('is-hidden', groups.length === 0 && !batch);
                confirmBtn.disabled = delivered || batchAlreadyDelivered || !groups.length || scannedCount === 0;
                anotherBatchBtn.hidden = !batch || delivered;
                anotherBatchBtn.textContent = batchAlreadyDelivered
                    ? 'Skip Delivered Batch'
                    : (scannedRolls.size ? 'Add Another Batch' : 'Discard Current Batch');
                anotherBatchBtn.disabled = delivered;
            }

            function renderScannedRows() {
                var groups = getDeliveryGroups();
                var completedIndex = 0;
                var rowsHtml = '';
                groups.forEach(function(group) {
                    var groupIndex = group.completed ? completedIndex++ : -1;
                    group.rows.forEach(function(row, rowIndex) {
                        rowsHtml += '<tr>';
                        if (group.completed && rowIndex === 0) {
                            rowsHtml += '<td rowspan="' + group.rows.length + '">' +
                                '<button class="remove-batch-button" type="button" data-remove-batch-index="' +
                                groupIndex + '" aria-label="Remove batch" title="Remove this batch"' +
                                (delivered ? ' disabled' : '') + '>Remove batch</button></td>';
                        } else if (!group.completed) {
                            var currentIndex = Array.from(scannedRolls.keys()).indexOf(String(row.ROLL));
                            rowsHtml += '<td><button class="remove-roll-button" type="button" data-remove-roll-index="' +
                                currentIndex + '" aria-label="Remove roll" title="Remove roll"' +
                                (delivered ? ' disabled' : '') + '>✕</button></td>';
                        }
                        if (rowIndex === 0) {
                            rowsHtml += '<td class="batch-card-cell" rowspan="' + group.rows.length + '">' +
                                escapeHtml(group.card_no) + '</td>';
                        }
                        rowsHtml += '<td><span class="roll-badge">' + escapeHtml(row.ROLL) + '</span></td>' +
                            '<td>' + escapeHtml(row.FTYPE) + '</td>' +
                            '<td>' + escapeHtml(formatQty(row.QTY)) + '</td>';
                        if (rowIndex === 0) {
                            rowsHtml += '<td rowspan="' + group.rows.length + '">' +
                                '<input class="remark-input" type="text" maxlength="255" data-remark-batch="' +
                                escapeHtml(group.card_no) + '" value="' + escapeHtml(group.remarks) + '"' +
                                (delivered ? ' disabled' : '') + '></td>';
                        }
                        rowsHtml += '</tr>';
                    });
                });

                document.getElementById('scannedRows').innerHTML = rowsHtml ||
                    '<tr><td colspan="6">Scan rolls after loading a batch card.</td></tr>';
                updateSummary();
            }

            function saveCurrentBatch() {
                if (!batch || !scannedRolls.size || batchAlreadyDelivered) return false;
                completedBatches.push({
                    card_no: batch.card_no,
                    target_qty: Number(batch.target_qty) || 0,
                    expected_count: batch.rows.length,
                    remarks: remarksByBatch.get(String(batch.card_no)) || '',
                    rows: Array.from(scannedRolls.values())
                });
                batch = null;
                scannedRolls.clear();
                expectedByRoll.clear();
                alreadyDeliveredRolls.clear();
                batchAlreadyDelivered = false;
                alreadyDeliveredMessage = '';
                cameraBtn.hidden = false;
                cameraBtn.disabled = false;
                cameraBtn.textContent = 'Start Camera Scanner';
                resumeCameraAfterRollRemoval = false;
                renderScannedRows();
                return true;
            }

            function removeScannedRoll(index) {
                if (delivered) return;
                var row = Array.from(scannedRolls.values())[index];
                if (!row) return;
                scannedRolls.delete(String(row.ROLL));
                renderScannedRows();
                showMessage('Roll ' + row.ROLL + ' removed. You can scan or enter it again.', 'info');
                if (!deliveryPanel.classList.contains('is-hidden')) {
                    cameraBtn.hidden = false;
                    cameraBtn.disabled = false;
                    cameraBtn.textContent = 'Start Camera Scanner';
                    rollInput.focus();
                    if (resumeCameraAfterRollRemoval) {
                        resumeCameraAfterRollRemoval = false;
                        startCamera();
                    }
                }
            }

            function removeCompletedBatch(index) {
                if (delivered) return;
                var savedBatch = completedBatches[index];
                if (!savedBatch) return;
                remarksByBatch.delete(String(savedBatch.card_no));
                completedBatches.splice(index, 1);
                renderScannedRows();
                showMessage('Batch ' + savedBatch.card_no + ' removed from this challan.', 'info');
            }

            function addScannedRoll(rawRoll) {
                var roll = extractRollValue(rawRoll);
                if (!roll || processingRoll || scanPopupActive) return;
                if (batch && scannedRolls.size >= batch.rows.length) return;
                if (!batch && completedBatches.some(function(savedBatch) {
                    return savedBatch.rows.some(function(row) {
                        return String(row.ROLL).toUpperCase() === roll.toUpperCase();
                    });
                })) {
                    showScanPopup('Roll ' + roll + ' is already included in this delivery challan.');
                    return;
                }
                if (delivered) {
                    showScanPopup(alreadyDeliveredMessage, 'Batch Already Delivered', 'error');
                    return;
                }
                if (batchAlreadyDelivered) {
                    showScanPopup(alreadyDeliveredMessage, 'Batch Already Delivered', 'error');
                    return;
                }
                processingRoll = true;
                rollInput.disabled = true;

                var resolveBatch = batch
                    ? Promise.resolve(batch)
                    : api('load_batch_by_roll', null, {roll: roll}).then(function(response) {
                        if (completedBatches.some(function(savedBatch) {
                            return String(savedBatch.card_no) === String(response.card_no);
                        })) {
                            throw new Error('Batch ' + response.card_no + ' is already included in this challan.');
                        }
                        batch = response;
                        expectedByRoll.clear();
                        alreadyDeliveredRolls = new Set((response.delivered_rolls || []).map(function(deliveredRoll) {
                            return String(deliveredRoll);
                        }));
                        batch.rows.forEach(function(row) {
                            expectedByRoll.set(String(row.ROLL), row);
                        });
                        document.getElementById('summaryCardNo').textContent = response.card_no;
                        document.getElementById('expectedCount').textContent = String(response.roll_count);
                        document.getElementById('expectedQty').textContent = formatQty(response.target_qty);
                        if (alreadyDeliveredRolls.size === response.rows.length) {
                            batchAlreadyDelivered = true;
                            alreadyDeliveredMessage = 'Batch Card ' + response.card_no + ' was already delivered on ' +
                                response.delivery.BUDAT + ' by ' + response.delivery.DOP +
                                ' (ID: ' + response.delivery.DID + ').';
                        } else {
                            var deliveredCount = alreadyDeliveredRolls.size;
                            showMessage(
                                'Batch Card ' + response.card_no + ' found. ' +
                                (deliveredCount ? deliveredCount + ' roll(s) already delivered. Scan remaining rolls.' : 'Scan its rolls.'),
                                'info'
                            );
                        }
                        scannedPanel.classList.remove('is-hidden');
                        updateSummary();
                        return response;
                    });

                resolveBatch.then(function() {
                    if (delivered) return;
                    if (batchAlreadyDelivered) {
                        showScanPopup(alreadyDeliveredMessage, 'Batch Already Delivered', 'error');
                        return;
                    }
                    if (!expectedByRoll.has(roll)) {
                        showScanPopup('Not match this roll for this batch: ' + roll);
                        return;
                    }
                    if (alreadyDeliveredRolls.has(roll)) {
                        showScanPopup('Roll ' + roll + ' has already been delivered.');
                        return;
                    }
                    if (scannedRolls.has(roll)) {
                        showScanPopup('Roll ' + roll + ' has already been scanned for this batch.');
                        return;
                    }

                    var row = expectedByRoll.get(roll);
                    var previousTotal = 0;
                    scannedRolls.forEach(function(scanned) { previousTotal += Number(scanned.QTY) || 0; });
                    var nextTotal = previousTotal + (Number(row.QTY) || 0);
                    var targetQty = Number(batch.target_qty);
                    if (nextTotal - targetQty > 0.0005) {
                        showScanPopup(
                            'Over QTY by ' + formatQty(nextTotal - targetQty) + ' KG. Batch total: ' +
                            formatQty(targetQty) + ' KG; previous scanned: ' +
                            formatQty(previousTotal) + ' KG; this roll: ' +
                            formatQty(row.QTY) + ' KG. This roll was not added.'
                        );
                        return;
                    }

                    scannedRolls.set(roll, row);
                    scannedPanel.classList.remove('is-hidden');
                    renderScannedRows();
                    var batchComplete = isCurrentBatchComplete();
                    if (batchComplete) {
                        resumeCameraAfterRollRemoval = Boolean(qrScanner && qrScanner.isScanning);
                        stopCamera();
                    }
                    showScanPopup(
                        batchComplete
                            ? 'All available rolls have been scanned successfully for Batch Card ' + batch.card_no + '.'
                            : 'Roll ' + roll + ' added successfully to Batch Card ' + batch.card_no + '.',
                        batchComplete ? 'Batch Scan Complete' : 'Roll Added Successfully',
                        'success',
                        1000
                    );
                }).catch(function(error) {
                    showScanPopup(error.message);
                }).then(function() {
                    finishScan();
                });
            }

            function finishScan() {
                processingRoll = false;
                rollInput.value = '';
                rollInput.disabled = delivered || scanPopupActive;
                if (!delivered && !scanPopupActive) rollInput.focus();
            }

            function stopCamera() {
                if (qrScanner && qrScanner.isScanning) {
                    return qrScanner.stop().catch(function(error) {
                        showScanPopup('Could not stop camera scanner: ' + error, 'Camera Error', 'error');
                    });
                }
                return Promise.resolve();
            }

            function startCamera() {
                if (typeof Html5Qrcode === 'undefined') {
                    showScanPopup('Camera scanner library did not load. Use the roll number input instead.', 'Camera Error', 'error');
                    return;
                }
                if (qrScanner && qrScanner.isScanning) return;
                cameraBtn.disabled = true;
                cameraBtn.textContent = 'Starting camera...';
                qrScanner = qrScanner || new Html5Qrcode('qr-reader');
                qrScanner.start(
                    {facingMode: cameraFacingMode},
                    {fps: 12, qrbox: {width: 250, height: 180}},
                    function(decodedText) { addScannedRoll(decodedText); },
                    function() {}
                ).then(function() {
                    cameraBtn.hidden = true;
                    showMessage('Camera scanner is ready. First scan a roll to find its batch.', 'info');
                }).catch(function(error) {
                    cameraBtn.disabled = false;
                    cameraBtn.hidden = false;
                    cameraBtn.textContent = 'Start Camera Scanner';
                    showScanPopup('Camera unavailable: ' + error + '. You can enter roll numbers manually.', 'Camera Unavailable', 'error');
                });
            }

            function switchCamera() {
                if (cameraSwitching || typeof Html5Qrcode === 'undefined') return;
                cameraSwitching = true;
                switchCameraBtn.disabled = true;
                var wasScanning = Boolean(qrScanner && qrScanner.isScanning);
                var stopPromise = wasScanning ? qrScanner.stop() : Promise.resolve();

                stopPromise.then(function() {
                    if (qrScanner) {
                        qrScanner.clear();
                        qrScanner = null;
                    }
                    cameraFacingMode = cameraFacingMode === 'environment' ? 'user' : 'environment';
                    switchCameraBtn.textContent = cameraFacingMode === 'environment'
                        ? 'Switch Camera'
                        : 'Switch Camera';
                    cameraSwitching = false;
                    switchCameraBtn.disabled = false;
                    if (wasScanning) startCamera();
                }).catch(function(error) {
                    cameraSwitching = false;
                    switchCameraBtn.disabled = false;
                    showScanPopup('Could not switch camera: ' + error, 'Camera Error', 'error');
                });
            }

            function numberToWords(value) {
                var ones = [
                    'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
                    'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
                    'seventeen', 'eighteen', 'nineteen'
                ];
                var tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
                var number = Math.floor(Math.max(0, Number(value) || 0));
                if (number < 20) return ones[number];
                if (number < 100) {
                    return tens[Math.floor(number / 10)] + (number % 10 ? ' ' + ones[number % 10] : '');
                }
                if (number < 1000) {
                    return ones[Math.floor(number / 100)] + ' hundred' +
                        (number % 100 ? ' ' + numberToWords(number % 100) : '');
                }
                var units = [
                    {value: 10000000, label: 'crore'},
                    {value: 100000, label: 'lakh'},
                    {value: 1000, label: 'thousand'}
                ];
                for (var index = 0; index < units.length; index++) {
                    if (number >= units[index].value) {
                        var count = Math.floor(number / units[index].value);
                        var remainder = number % units[index].value;
                        return numberToWords(count) + ' ' + units[index].label +
                            (remainder ? ' ' + numberToWords(remainder) : '');
                    }
                }
                return '';
            }

            function renderChallan(response) {
                var groups = getDeliveryGroups();
                var totalQty = 0;
                var rowsHtml = '';
                var serial = 0;
                groups.forEach(function(group) {
                    group.rows.forEach(function(row, index) {
                        serial++;
                        totalQty += Number(row.QTY) || 0;
                        rowsHtml += '<tr><td>' + serial + '</td>' +
                            (index === 0
                                ? '<td rowspan="' + group.rows.length + '">' + escapeHtml(group.card_no) + '</td>'
                                : '') +
                            '<td>' + escapeHtml(row.FTYPE) + '</td>' +
                            '<td>' + escapeHtml(row.ROLL) + '</td>' +
                            '<td>' + escapeHtml(formatQty(row.QTY)) + '</td>' +
                            (index === 0
                                ? '<td rowspan="' + group.rows.length + '">' + escapeHtml(group.remarks) + '</td>'
                                : '') +
                            '</tr>';
                    });
                });
                var dateParts = String(response.delivery_date || '').split('-');
                var displayDate = dateParts.length === 3 ? dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0] : '';
                var totalMilliKg = Math.round(totalQty * 1000);
                var wholeKg = Math.floor(totalMilliKg / 1000);
                var grams = totalMilliKg % 1000;
                var qtyWords = numberToWords(wholeKg) + ' kilogram' +
                    (wholeKg === 1 ? '' : 's');
                if (grams) qtyWords += ' and ' + numberToWords(grams) + ' grams';
                qtyWords += ' only';

                document.getElementById('challanPrint').innerHTML =
                    '<article class="challan-paper">' +
                    '<div class="challan-paper-header">' +
                    '<div class="challan-brand">PURBANI</div>' +
                    '<div class="challan-company"><h1>PURBANI FABRICS LTD.</h1>' +
                    '<p>NOORBAGH, MOUCHAK, KALIAKOIR, GAZIPUR</p>' +
                    '<div class="challan-title">DELIVERY CHALLAN</div></div><div></div></div>' +
                    '<div class="challan-meta">' +
                    '<div class="challan-to"><strong>To:</strong> ' + escapeHtml(response.to_name || '') + '</div>' +
                    '<div><strong>Challan No.:</strong> ' + escapeHtml(response.challan_no) + '</div>' +
                    '<div><strong>Date:</strong> ' + escapeHtml(displayDate) + '</div>' +
                    '<div><strong>Gate Pass No.:</strong> ' + escapeHtml(response.gate_pass_no || '-') + '</div>' +
                    '<div><strong>Vehicle No.:</strong> ' + escapeHtml(response.vehicle_no || '-') + '</div>' +
                    '</div><table><thead><tr><th>SL. No.</th><th>Batch No.</th><th>Description of Goods</th>' +
                    '<th>Roll/Bag</th><th>Qnty.</th><th>Remarks</th></tr></thead><tbody>' + rowsHtml +
                    '</tbody><tfoot><tr><th colspan="4">TOTAL</th><th>' +
                    escapeHtml(formatQty(totalQty)) + '</th><th></th></tr></tfoot></table>' +
                    '<div class="challan-notes"><div><strong>Total weight:</strong> ' +
                    escapeHtml(formatQty(totalQty)) + ' KG</div><div><strong>In words:</strong> ' +
                    escapeHtml(qtyWords) + '</div></div>' +
                    '<div class="challan-signatures"><span>Received by</span><span>Prepared by</span>' +
                    '<span>Store Officer</span><span>Store Manager</span><span>Auth. Signature</span></div></article>';
            }

            rollForm.addEventListener('submit', function(event) {
                event.preventDefault();
                addScannedRoll(rollInput.value);
            });
            rollInput.addEventListener('keydown', function(event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                addScannedRoll(rollInput.value);
            });

            document.getElementById('scannedRows').addEventListener('click', function(event) {
                var button = event.target.closest('[data-remove-roll-index], [data-remove-batch-index]');
                if (!button || !this.contains(button)) return;
                if (button.hasAttribute('data-remove-roll-index')) {
                    removeScannedRoll(Number(button.dataset.removeRollIndex));
                } else {
                    removeCompletedBatch(Number(button.dataset.removeBatchIndex));
                }
            });
            document.getElementById('scannedRows').addEventListener('input', function(event) {
                var input = event.target.closest('[data-remark-batch]');
                if (!input || !this.contains(input)) return;
                remarksByBatch.set(String(input.dataset.remarkBatch), input.value);
            });

            cameraBtn.addEventListener('click', startCamera);
            switchCameraBtn.addEventListener('click', switchCamera);
            anotherBatchBtn.addEventListener('click', function() {
                if (batchAlreadyDelivered || !scannedRolls.size) {
                    if (batch) remarksByBatch.delete(String(batch.card_no));
                    batch = null;
                    scannedRolls.clear();
                    expectedByRoll.clear();
                    alreadyDeliveredRolls.clear();
                    batchAlreadyDelivered = false;
                    alreadyDeliveredMessage = '';
                    cameraBtn.hidden = false;
                    cameraBtn.disabled = false;
                    cameraBtn.textContent = 'Start Camera Scanner';
                    renderScannedRows();
                    showMessage('Current batch skipped. Scan a roll from another batch.', 'info');
                    rollInput.focus();
                    return;
                }
                if (!saveCurrentBatch()) return;
                showMessage('Batch added to this challan. Scan a roll from the next batch.', 'success');
                rollInput.focus();
            });
            document.getElementById('printChallanBtn').addEventListener('click', function() {
                if (!delivered) return;
                window.print();
            });

            confirmBtn.addEventListener('click', function() {
                if (confirmBtn.disabled) return;
                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Confirming...';
                var deliveryGroups = getDeliveryGroups();
                var toName = document.getElementById('toNameInput').value.trim();
                var gatePassNo = document.getElementById('gatePassNoInput').value.trim();
                var vehicleNo = document.getElementById('vehicleNoInput').value.trim();
                api('confirm_delivery', {
                    batches: deliveryGroups.map(function(group) {
                        return {
                            card_no: group.card_no,
                            rolls: group.rows.map(function(row) { return String(row.ROLL); }),
                            total_qty: Number(group.target_qty),
                            remarks: group.remarks
                        };
                    }),
                    to_name: toName,
                    gate_pass_no: gatePassNo,
                    vehicle_no: vehicleNo
                }).then(function(response) {
                    delivered = true;
                    renderScannedRows();
                    rollInput.disabled = true;
                    document.getElementById('toNameInput').disabled = true;
                    document.getElementById('gatePassNoInput').disabled = true;
                    document.getElementById('vehicleNoInput').disabled = true;
                    confirmBtn.textContent = 'Delivery Challan Confirmed';
                    document.getElementById('printChallanBtn').hidden = false;
                    renderChallan(response);
                    var confirmationMessage = response.challan_no +
                        ' successfully confirmed. ' +
                        response.roll_count + ' roll(s), ' + formatQty(response.total_qty) + ' KG.';
                    showScanPopup(confirmationMessage, 'Delivery Confirmed', 'success', 3000);
                    stopCamera();
                }).catch(function(error) {
                    showScanPopup(error.message, 'Batch Delivery Error', 'error');
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Confirm Delivery Challan';
                });
            });

            rollInput.focus();
            startCamera();
        })();
    </script>
</body>
</html>
