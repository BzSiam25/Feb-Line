<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit;
}

// Start each page load with a fresh batch so old session rolls never linger
if (!isset($_SESSION['dyeing_batch']) || !is_array($_SESSION['dyeing_batch'])) {
    $_SESSION['dyeing_batch'] = [];
}
$_SESSION['dyeing_batch']['rolls'] = [];
unset($_SESSION['dyeing_batch']['card_no']);
unset($_SESSION['dyeing_batch']['created_at']);
unset($_SESSION['dyeing_batch']['target_qty']);
unset($_SESSION['dyeing_batch']['processes']);
unset($_SESSION['dyeing_batch']['saved']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dyeing | Batch Card</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            background: linear-gradient(135deg, #f0fdf9, #e0f2fe, #f5f3ff);
            min-height: 100vh;
            padding: 16px;
        }

        .wrap {
            width: 100%;
            max-width: none;
            margin: 0 auto;
        }

        .top-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #134e4a, #0f766e, #0d9488);
            color: #fff;
            padding: 18px 22px;
            border-radius: 18px;
            margin-bottom: 18px;
            flex-wrap: wrap;
            position: relative;
            justify-content: space-between;
        }

        .top-bar h1 {
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }

        .card-no-badge {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 6px 14px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(15, 118, 110, 0.1);
            padding: 20px;
            margin-bottom: 18px;
        }

        .panel-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #134e4a;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 14px;
        }

        .batch-search-layout {
            display: grid;
            grid-template-columns: minmax(440px, 1fr) minmax(620px, 1.2fr);
            align-items: stretch;
            gap: 16px;
        }

        #batchSearchPanel {
            container-type: inline-size;
        }

        .search-controls {
            display: grid;
            gap: 12px;
            min-width: 0;
        }

        .search-po-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .search-action-row {
            display: grid;
            grid-template-columns: minmax(150px, 1fr) minmax(170px, 1fr) auto;
            align-items: end;
            gap: 10px;
        }

        .process-panel {
            min-width: 0;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #f8fffd;
        }

        .process-panel h3 {
            margin: 0 0 10px;
            color: #134e4a;
            font-size: .95rem;
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(180px, 1.2fr);
            gap: 8px 10px;
        }

        .others-detail input {
            width: 100%;
            min-height: 34px;
            padding: 7px 9px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font-size: .82rem;
            outline: none;
        }

        .others-detail input:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, .12);
        }

        .others-detail input:disabled {
            background: #f1f5f9;
        }

        .process-others {
            grid-column: 4;
            grid-row: 3;
        }

        .process-nonsilicon {
            grid-column: 3;
            grid-row: 4;
        }

        .process-peach {
            grid-column: 4;
            grid-row: 1;
        }

        .process-shearing {
            grid-column: 4;
            grid-row: 2;
        }

        .others-detail {
            min-width: 0;
            grid-column: 4;
            grid-row: 4;
        }

        .others-detail input {
            min-width: 0;
        }

        .process-option {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            min-width: 0;
            color: #334155;
            font-size: .8rem;
            font-weight: 650;
            line-height: 1.3;
            white-space: nowrap;
            cursor: pointer;
        }

        .process-option input {
            flex: 0 0 auto;
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: #0d9488;
        }

        .process-panel:disabled {
            opacity: .58;
        }

        .process-panel:disabled .process-option {
            cursor: not-allowed;
        }

        .filter-field {
            min-width: 0;
        }

        .filter-field.target-qty-field {
            min-width: 0;
        }

        .filter-field label {
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .filter-field-label {
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .search-input {
            flex: 1;
            min-width: 220px;
            padding: 11px 16px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
            outline: none;
        }

        .search-input:focus,
        .filter-select:focus,
        .qty-input:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }

        .filter-select,
        .qty-input {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            color: #0f172a;
            font-size: 0.95rem;
            outline: none;
        }

        .filter-select:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .po-input {
            cursor: text !important;
        }

        .suggestion-preview {
            margin-top: 16px;
        }

        .suggestion-preview table {
            width: 100%;
        }

        .suggestion-preview th,
        .suggestion-preview td {
            padding: 8px 14px;
        }

        .is-hidden {
            display: none !important;
        }

        .loading-note {
            color: #0f766e;
            font-weight: 600;
        }

        @media (max-width: 720px) {
            .batch-search-layout {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @container (max-width: 1100px) {
            .batch-search-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            .process-panel {
                width: 100%;
            }
        }

        @media (max-width: 700px) {
            .process-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .process-others,
            .others-detail,
            .process-nonsilicon,
            .process-peach,
            .process-shearing {
                grid-column: auto;
                grid-row: auto;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .panel {
                padding: 12px;
            }

            .search-po-row,
            .search-action-row {
                grid-template-columns: minmax(0, 1fr);
            }

            .search-action-row > .btn {
                width: 100%;
            }
        }

        .btn {
            border: none;
            cursor: pointer;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .search-row > .btn {
            min-height: 42px;
            justify-content: center;
            align-self: end;
            white-space: nowrap;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-green {
            background: linear-gradient(135deg, #059669, #10b981);
        }

        .btn-blue {
            background: linear-gradient(135deg, #0284c7, #38bdf8);
        }

        .btn-amber {
            background: linear-gradient(135deg, #d97706, #f59e0b);
        }

        .btn-slate {
            background: linear-gradient(135deg, #475569, #64748b);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .status-line {
            margin: 10px 0;
            font-weight: 600;
            color: #0f766e;
        }

        .table-scroll {
            overflow-x: auto;
        }

        #searchResults .table-scroll > table {
            width: 100%;
            min-width: 1500px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }

        thead th {
            background: #134e4a;
            color: #fff;
            padding: 12px 10px;
            text-align: left;
            font-weight: 700;
            white-space: nowrap;
        }

        tbody td {
            padding: 11px 10px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
            vertical-align: middle;
        }

        tfoot th,
        tfoot td {
            padding: 12px 10px;
            border-top: 2px solid #cbd5e1;
            background: #f0fdfa;
            color: #134e4a;
            font-weight: 800;
            white-space: nowrap;
        }

        tfoot .total-label {
            text-align: right;
        }

        tfoot .selected-total-value {
            text-align: left;
            padding-left: 24px;
        }

        .roll-select-cell {
            width: 48px;
            text-align: center;
        }

        .roll-select-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #0d9488;
            cursor: pointer;
        }

        .required-qty-input {
            width: 92px;
            min-height: 34px;
            padding: 6px 9px;
            border: 1px solid #b8c8d8;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font: inherit;
            font-weight: 650;
            text-align: right;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, .04);
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .required-qty-input:focus {
            border-color: #0d9488;
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, .14);
        }

        .required-qty-input:disabled {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        .remove-roll-btn {
            border: 0;
            border-radius: 8px;
            padding: 7px 10px;
            background: #fee2e2;
            color: #b91c1c;
            font-weight: 700;
            cursor: pointer;
        }

        .remove-roll-btn:hover {
            background: #fecaca;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr:hover {
            background: #ecfdf5;
        }

        .roll-badge {
            background: #ccfbf1;
            color: #065f46;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 100px;
            display: inline-block;
        }

        .add-btn {
            background: #d1fae5;
            color: #047857;
            border: none;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
        }

        .add-btn:hover {
            background: #a7f3d0;
        }

        .add-btn:disabled {
            background: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
        }

        .delete-btn {
            background: #fee2e2;
            color: #b91c1c;
            border: none;
            cursor: pointer;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .delete-btn:hover {
            background: #fecaca;
            transform: scale(1.08);
        }

        .empty-row td {
            text-align: center;
            color: #94a3b8;
            padding: 28px;
            font-weight: 600;
        }

        .footer-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 16px;
        }

        .msg-box {
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
            margin-bottom: 12px;
            display: none;
        }

        .msg-success {
            background: #dcfce7;
            color: #166534;
            display: block;
        }

        .msg-error {
            background: #fee2e2;
            color: #991b1b;
            display: block;
        }

        .added-tag {
            background: #e2e8f0;
            color: #64748b;
            padding: 3px 10px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.75rem;
        }

        .card-group-row {
            background: #0f766e;
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .card-group-row em {
            font-style: normal;
            opacity: 0.85;
            font-weight: 500;
        }

        .filter-select {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 7px 10px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            color: #134e4a;
            outline: none;
        }

        .filter-select:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 18px;
            background: black;
            color: white;
            border: 2px solid black;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.25s ease;
        }

        .back-btn:hover {
            background: white;
            color: black;
            border-color: black;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top-bar">
            <a href="initialPage.php" class="back-btn" id="backBtn"><i class="fas fa-arrow-left"></i> Back</a>

            <h1><i class="fa-solid fa-vial-circle-check"></i> Dyeing Batch Card</h1>
            <span class="card-no-badge"><i class="fa-solid fa-hashtag"></i> Batch Card: <span id="cardNo">-</span></span>
        </div>

        <div id="msgBox" class="msg-box" role="status" aria-live="polite"></div>

        <div class="panel" id="batchSearchPanel">
            <div class="panel-title"><i class="fa-solid fa-filter"></i> Find Rolls for Batch Card</div>
            <div class="batch-search-layout">
                <div class="search-controls">
                    <div class="search-po-row">
                        <div class="filter-field">
                            <label for="poNumberSelect">PO Number</label>
                            <input type="text" id="poNumberSelect" class="filter-select po-input"
                                list="poNumberOptions" autocomplete="off" placeholder="Enter or select PO Number">
                            <datalist id="poNumberOptions"></datalist>
                        </div>
                        <div class="filter-field">
                            <label for="colorSelect">Color</label>
                            <select id="colorSelect" class="filter-select" disabled>
                                <option value="">Select a PO Number first</option>
                            </select>
                        </div>
                    </div>
                    <div class="search-action-row search-row">
                        <div class="filter-field target-qty-field">
                            <label for="targetQtyInput">Target QTY (KG)</label>
                            <input type="number" id="targetQtyInput" class="qty-input" min="0.001" step="0.001" placeholder="Enter target QTY">
                        </div>
                        <div class="filter-field target-qty-field">
                            <label for="dyeingMcnoSelect">Dyeing MC No (Required)</label>
                            <select id="dyeingMcnoSelect" class="filter-select" disabled>
                                <option value="">Loading dyeing machines...</option>
                            </select>
                        </div>
                        <button class="btn btn-blue" id="searchBtn" type="button" disabled>
                            <i class="fa-solid fa-magnifying-glass"></i> Search
                        </button>
                    </div>
                </div>
                <fieldset class="process-panel" id="processPanel" disabled>
                    <h3>Dyeing Process</h3>
                    <div class="process-grid">
                        <label class="process-option"><input type="checkbox" data-process-field="SILICON"> Silicon</label>
                        <label class="process-option"><input type="checkbox" data-process-field="ENZYME"> Enzyme</label>
                        <label class="process-option"><input type="checkbox" data-process-field="AOP"> AOP</label>
                        <label class="process-option process-nonsilicon"><input type="checkbox" data-process-field="NONSILICON"> Non-Silicon</label>
                        <label class="process-option"><input type="checkbox" data-process-field="NONENZYME"> Non-Enzyme</label>
                        <label class="process-option"><input type="checkbox" data-process-field="SOFTENER"> Softener</label>
                        <label class="process-option"><input type="checkbox" data-process-field="NWASH"> Normal Wash (YD)</label>
                        <label class="process-option process-peach"><input type="checkbox" data-process-field="PEACH"> Peach</label>
                        <label class="process-option"><input type="checkbox" data-process-field="NONSOFTENER"> Non-Softener</label>
                        <label class="process-option"><input type="checkbox" data-process-field="EWASH"> Enzyme Wash (YD)</label>
                        <label class="process-option"><input type="checkbox" data-process-field="BRUSH"> Brush</label>
                        <label class="process-option process-shearing"><input type="checkbox" data-process-field="SHEARING"> Shearing</label>
                        <label class="process-option"><input type="checkbox" data-process-field="SINGEING"> Singeing</label>
                        <label class="process-option"><input type="checkbox" data-process-field="HEAT_SET"> Heat Set</label>
                        <label class="process-option process-others"><input type="checkbox" data-process-field="OTHERS"> Others</label>
                        <div class="others-detail is-hidden" id="othersDetail">
                            <input type="text" id="othersProcessInput" maxlength="100"
                                placeholder="Enter other process" aria-label="Other dyeing process" disabled>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <div class="panel is-hidden" id="searchResults">
            <div class="panel-title">
                <i class="fa-solid fa-table-list"></i> Matching Rolls
                <span class="card-no-badge" id="resultCount">0 roll</span>
            </div>
            <div class="status-line" id="resultsStatus"></div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th class="roll-select-cell">Select</th>
                            <th>ROLL</th>
                            <th>Rack</th>
                            <th>PO Number</th>
                            <th>Knitting QTY</th>
                            <th>Available QTY</th>
                            <th>Required QTY</th>
                            <th>Color</th>
                            <th>SONO</th>
                            <th>Buyer</th>
                            <th>Style</th>
                            <th>MC Dia</th>
                            <th>Customer</th>
                            <th>Open / Tube</th>
                            <th>SL/VQ</th>
                            <th>Fabrics Type</th>
                            <th>Finish GSM</th>
                            <th>Finish Dia</th>
                            <th>Yarn Type</th>
                            <th>Yarn Count</th>
                            <th>Yarn Brand</th>
                        </tr>
                    </thead>
                    <tbody id="storeBody">
                        <tr class="empty-row"><td colspan="21">Select PO Number and Color, then search.</td></tr>
                    </tbody>
                    <tfoot id="storeFoot"></tfoot>
                </table>
            </div>
        </div>

        <div class="panel is-hidden" id="suggestionPanel">
            <div class="panel-title"><i class="fa-solid fa-file-circle-plus"></i> Create Batch Card with Rolls:</div>
            <div class="suggestion-preview" id="suggestionPreview">
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ROLL</th><th>Rack</th><th>Knitting QTY</th><th>Available QTY</th><th>Required QTY</th><th>ACTION</th></tr></thead>
                        <tbody id="suggestionRolls">
                            <tr class="empty-row"><td colspan="6">Select rolls from the Matching Rolls table above.</td></tr>
                        </tbody>
                        <tfoot id="selectedRollsFoot"></tfoot>
                    </table>
                </div>
                <div class="footer-actions">
                    <button class="btn btn-green" id="confirmCreateBtn" type="button">
                        <i class="fa-solid fa-check"></i> Confirm &amp; Create Batch Card
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        var candidateRolls = [];
        var selectedRolls = new Map();
        var colorRequestId = 0;
        var searchRequestId = 0;
        var searchInProgress = false;
        var poInputTimer = null;
        var targetQtyTimer = null;
        var batchMessageTimer = null;
        var poNumberSelect = document.getElementById('poNumberSelect');
        var colorSelect = document.getElementById('colorSelect');
        var searchBtn = document.getElementById('searchBtn');
        var targetQtyInput = document.getElementById('targetQtyInput');
        var dyeingMcnoSelect = document.getElementById('dyeingMcnoSelect');
        var confirmCreateBtn = document.getElementById('confirmCreateBtn');
        var processPanel = document.getElementById('processPanel');
        function showMsg(message, type) {
            var box = document.getElementById('msgBox');
            box.className = 'msg-box ' + (type === 'error' ? 'msg-error' : 'msg-success');
            box.textContent = message;
        }

        function hideMsg() {
            var box = document.getElementById('msgBox');
            box.className = 'msg-box';
            box.textContent = '';
        }

        function showBatchPopup(message, type) {
            var box = document.getElementById('msgBox');
            if (batchMessageTimer) clearTimeout(batchMessageTimer);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: type === 'error' ? 'error' : 'success',
                    title: type === 'error' ? 'Batch Card Not Created' : 'Successfully Saved!',
                    text: message,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    allowOutsideClick: type !== 'success',
                    allowEscapeKey: type !== 'success'
                }).then(function() {
                    if (type === 'success') window.location.reload();
                });
                return;
            }

            box.className = 'msg-box ' + (type === 'error' ? 'msg-error' : 'msg-success');
            box.textContent = message;
            batchMessageTimer = setTimeout(function() {
                if (type === 'success') {
                    window.location.reload();
                    return;
                }
                hideMsg();
                batchMessageTimer = null;
            }, 3000);
        }

        function esc(value) {
            if (value === null || value === undefined || value === '') return '-';
            return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function readJson(response) {
            return response.json().then(function(data) {
                if (!response.ok) {
                    throw new Error(data.message || 'Server request failed');
                }
                return data;
            });
        }

        function apiGet(action, params) {
            var query = new URLSearchParams(params || {});
            query.set('action', action);
            return fetch('ajaxDyeing_batch_card.php?' + query.toString()).then(readJson);
        }

        function apiPost(action, payload) {
            return fetch('ajaxDyeing_batch_card.php?action=' + encodeURIComponent(action), {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload || {})
            }).then(readJson);
        }

        function setProcessSelectionEnabled(enabled) {
            processPanel.disabled = !enabled;
            if (!enabled) {
                processPanel.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                document.getElementById('othersProcessInput').value = '';
                document.getElementById('othersProcessInput').disabled = true;
                document.getElementById('othersDetail').classList.add('is-hidden');
            }
        }

        function getSelectedProcesses() {
            var selected = {};
            processPanel.querySelectorAll('input[data-process-field]:checked').forEach(function(checkbox) {
                var field = checkbox.dataset.processField;
                selected[field] = field === 'OTHERS'
                    ? (document.getElementById('othersProcessInput').value.trim() || 'Others')
                    : field;
            });
            return selected;
        }

        function resetResults(preserveTargetQty) {
            searchRequestId++;
            searchInProgress = false;
            candidateRolls = [];
            selectedRolls.clear();
            setProcessSelectionEnabled(false);
            document.getElementById('searchResults').classList.add('is-hidden');
            document.getElementById('suggestionPanel').classList.add('is-hidden');
            document.getElementById('storeBody').innerHTML =
                '<tr class="empty-row"><td colspan="22">Select PO Number and Color, then search.</td></tr>';
            document.getElementById('storeFoot').innerHTML = '';
            document.getElementById('resultCount').textContent = '0 roll';
            if (!preserveTargetQty) {
                targetQtyInput.value = '';
            }
            confirmCreateBtn.disabled = true;
            document.getElementById('suggestionRolls').innerHTML =
                '<tr class="empty-row"><td colspan="4">Select rolls from the Matching Rolls table above.</td></tr>';
            document.getElementById('selectedRollsFoot').innerHTML = '';
        }

        function loadPoNumbers() {
            var options = document.getElementById('poNumberOptions');
            apiGet('po_numbers').then(function(response) {
                if (!response.success) throw new Error(response.message || 'Could not load PO Numbers');
                options.innerHTML = '';
                (response.po_numbers || []).forEach(function(poNumber) {
                    var option = document.createElement('option');
                    option.value = poNumber;
                    options.appendChild(option);
                });
                if (!response.po_numbers || response.po_numbers.length === 0) {
                    showMsg('No PO Numbers with available rolls were found.', 'error');
                }
            }).catch(function(error) {
                showMsg(error.message, 'error');
            });
        }

        function loadColors(poNumber) {
            var requestId = ++colorRequestId;
            resetResults();
            colorSelect.disabled = true;
            searchBtn.disabled = true;
            colorSelect.innerHTML = '<option value="">Loading Colors...</option>';
            if (!poNumber) {
                colorSelect.innerHTML = '<option value="">Select a PO Number first</option>';
                return;
            }

            apiGet('colors', {po_number: poNumber}).then(function(response) {
                if (requestId !== colorRequestId) return;
                if (!response.success) throw new Error(response.message || 'Could not load Colors');
                colorSelect.innerHTML = '<option value="">Select Color</option>';
                (response.colors || []).forEach(function(color) {
                    var option = document.createElement('option');
                    option.value = color;
                    option.textContent = color;
                    colorSelect.appendChild(option);
                });
                if (!response.colors || response.colors.length === 0) {
                    colorSelect.innerHTML = '<option value="">No available Color for this PO</option>';
                }
                colorSelect.disabled = !response.colors || response.colors.length === 0;
            }).catch(function(error) {
                if (requestId !== colorRequestId) return;
                colorSelect.innerHTML = '<option value="">Unable to load Colors</option>';
                showMsg(error.message, 'error');
            });
        }

        function qtyUnits(row) {
            var qty = Number(row.RQTY);
            return Number.isFinite(qty) && qty > 0 ? Math.round(qty * 1000) : 0;
        }

        function availableQtyUnits(row) {
            var qty = Number(row.AQTY);
            return Number.isFinite(qty) && qty > 0 ? Math.round(qty * 1000) : 0;
        }

        function knittingQtyUnits(row) {
            var qty = Number(row.KQTY);
            return Number.isFinite(qty) && qty > 0 ? Math.round(qty * 1000) : 0;
        }

        function formatQty(value) {
            var qty = Number(value);
            return Number.isFinite(qty)
                ? qty.toFixed(3).replace(/\.?0+$/, '')
                : '';
        }

        function targetUnits() {
            var target = Number(targetQtyInput.value);
            return Number.isFinite(target) && target > 0 ? Math.round(target * 1000) : 0;
        }

        function syncTargetQtyFromSelection() {
            var selectedTotal = Array.from(selectedRolls.values()).reduce(function(sum, row) {
                return sum + qtyUnits(row);
            }, 0);
            targetQtyInput.value = selectedTotal > 0
                ? (selectedTotal / 1000).toFixed(3).replace(/\.?0+$/, '')
                : '';
        }

        function renderMatchingRollsFoot() {
            var totals = candidateRolls.reduce(function(sum, row) {
                sum.knitting += knittingQtyUnits(row);
                sum.available += availableQtyUnits(row);
                var selectedRow = selectedRolls.get(String(row.ROLL));
                if (selectedRow) sum.required += qtyUnits(selectedRow);
                return sum;
            }, {knitting: 0, available: 0, required: 0});
            document.getElementById('storeFoot').innerHTML =
                '<tr><th colspan="4" class="total-label">Total QTY:</th>' +
                '<th>' + formatQty(totals.knitting / 1000) + ' KG</th>' +
                '<th id="availableQtyTotal">' + formatQty(totals.available / 1000) + ' KG</th>' +
                '<th>' + formatQty(totals.required / 1000) + ' KG</th>' +
                '<th colspan="14"></th></tr>';
        }

        function renderRolls(rolls) {
            var fields = [
                ['ROLL', 'ROLL'], ['RACK', 'RACK'], ['PO_NUMBER', 'PO_NUMBER'],
                ['KQTY', 'KQTY'], ['AQTY', 'AQTY'], ['RQTY', 'RQTY'],
                ['COLOR', 'COLOR'], ['SONO', 'SONO'], ['BUYER', 'BUYER'], ['STYLE', 'STYLE'],
                ['MCDIA', 'MCDIA'], ['CUSTOMER', 'CUSTOMER'], ['O_T', 'O_T'], ['SL', 'SL'],
                ['FTYPE', 'FTYPE'], ['FGSM', 'FGSM'], ['FDIA', 'FDIA'], ['YTYPE', 'YTYPE'],
                ['YCOUNT', 'YCOUNT'], ['YBRAND', 'YBRAND']
            ];
            var html = '';
            rolls.forEach(function(row, index) {
                var rollKey = String(row.ROLL);
                html += '<tr><td class="roll-select-cell"><input type="checkbox" class="roll-select-checkbox" data-roll-index="' +
                    index + '" aria-label="Select roll ' + esc(rollKey) + '"' +
                    (selectedRolls.has(rollKey) ? ' checked' : '') + '></td>' +
                    fields.map(function(field) {
                    if (field[0] === 'ROLL') {
                        return '<td><span class="roll-badge">' + esc(row.ROLL) + '</span></td>';
                    }
                    if (field[0] === 'RQTY') {
                        var selectedRow = selectedRolls.get(rollKey);
                        var requiredQty = selectedRow ? selectedRow.RQTY : row.AQTY;
                        return '<td><input type="number" class="required-qty-input" data-required-index="' +
                            index + '" min="0.001" max="' + esc(row.AQTY) +
                            '" step="0.001" value="' + esc(formatQty(requiredQty)) + '"' +
                            (selectedRow ? '' : ' disabled') + ' aria-label="Required QTY for roll ' +
                            esc(rollKey) + '"></td>';
                    }
                    return '<td>' + (['KQTY', 'AQTY'].indexOf(field[0]) >= 0
                        ? esc(formatQty(row[field[1]]))
                        : esc(row[field[1]])) + '</td>';
                }).join('') + '</tr>';
            });
            document.getElementById('storeBody').innerHTML = html ||
                '<tr class="empty-row"><td colspan="21">No available rolls found for this PO Number and Color.</td></tr>';
            renderMatchingRollsFoot();
            document.querySelectorAll('.roll-select-checkbox').forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    var row = candidateRolls[Number(this.dataset.rollIndex)];
                    if (row) toggleRollSelection(row, this.checked, this);
                });
            });
            document.querySelectorAll('.required-qty-input').forEach(function(input) {
                input.addEventListener('input', function() {
                    var row = candidateRolls[Number(this.dataset.requiredIndex)];
                    if (!row) return;
                    var requiredQty = Number(this.value);
                    var rollKey = String(row.ROLL);
                    if (!selectedRolls.has(rollKey)) return;
                    if (!Number.isFinite(requiredQty) || requiredQty <= 0 ||
                        requiredQty - Number(row.AQTY) > 0.000001) {
                        selectedRolls.get(rollKey).RQTY = 0;
                    } else {
                        selectedRolls.get(rollKey).RQTY = requiredQty;
                        syncTargetQtyFromSelection();
                        hideMsg();
                    }
                    updateCreateButton();
                });
                input.addEventListener('change', function() {
                    var row = candidateRolls[Number(this.dataset.requiredIndex)];
                    if (!row) return;
                    var requiredQty = Number(this.value);
                    if (!Number.isFinite(requiredQty) || requiredQty <= 0 ||
                        requiredQty - Number(row.AQTY) > 0.000001) {
                        showBatchPopup(
                            'Batch Card was NOT created. Required QTY must be greater than 0 and cannot exceed Available QTY (' +
                                row.AQTY + ' KG) for roll ' + row.ROLL + '.',
                            'error'
                        );
                    }
                });
            });
            document.getElementById('resultCount').textContent =
                rolls.length + ' available roll' + (rolls.length === 1 ? '' : 's');
        }

        function updateCreateButton() {
            var qty = targetUnits();
            var selected = Array.from(selectedRolls.values());
            var selectedTotal = selected.reduce(function(sum, row) {
                return sum + qtyUnits(row);
            }, 0);
            document.getElementById('suggestionPanel').classList.toggle(
                'is-hidden',
                candidateRolls.length === 0 || qty <= 0
            );
            var hasValidSelectedQty = selected.length > 0 &&
                selected.every(function(row) {
                    return qtyUnits(row) > 0 &&
                        Number(row.RQTY) <= Number(row.AQTY) + 0.000001;
                });
            var selectedProcessCheckboxes = processPanel.querySelectorAll('input[data-process-field]:checked');
            var hasSelectedProcess = selectedProcessCheckboxes.length > 0;
            confirmCreateBtn.disabled = !hasValidSelectedQty || !dyeingMcnoSelect.value ||
                !hasSelectedProcess;
            confirmCreateBtn.title = !dyeingMcnoSelect.value
                ? 'Select a Dyeing MC No to enable batch creation.'
                : !hasValidSelectedQty
                    ? 'Select at least one roll with a valid Required QTY.'
                    : !hasSelectedProcess
                        ? 'Select at least one Dyeing Process.'
                        : 'Create the batch card.';

            var status = document.getElementById('resultsStatus');
            if (candidateRolls.length === 0) {
                status.textContent = 'No available rolls match this PO Number and Color.';
            } else if (qty > 0) {
                status.textContent = 'Available total: ' +
                    formatQty(candidateRolls.reduce(function(sum, row) { return sum + availableQtyUnits(row); }, 0) / 1000) +
                    ' KG. Selected total: ' + formatQty(selectedTotal / 1000) +
                    ' KG; target: ' + formatQty(qty / 1000) + ' KG.';
            } else {
                status.textContent = 'Available total: ' +
                    formatQty(candidateRolls.reduce(function(sum, row) { return sum + availableQtyUnits(row); }, 0) / 1000) +
                    ' KG. Select rolls to automatically fill Target QTY.';
            }
            renderMatchingRollsFoot();
            renderSelectedRolls();
        }

        function buildSuggestedRolls(target) {
            var available = candidateRolls.reduce(function(sum, row) {
                return sum + availableQtyUnits(row);
            }, 0);
            var minimum = Math.max(0, target - 5000);
            var maximum = Math.min(available, target + 5000);
            if (maximum < minimum || maximum > 5000000) return null;

            var stateNodes = new Int32Array(maximum + 1);
            stateNodes.fill(-2);
            stateNodes[0] = -1;
            var previousNodes = [];
            var rowIndexes = [];
            var reachableSums = [0];

            candidateRolls.forEach(function(row, rowIndex) {
                var units = availableQtyUnits(row);
                if (units <= 0 || units > maximum) return;

                var countBeforeRow = reachableSums.length;
                for (var i = countBeforeRow - 1; i >= 0; i--) {
                    var sum = reachableSums[i];
                    var next = sum + units;
                    if (next > maximum || stateNodes[next] !== -2) continue;

                    previousNodes.push(stateNodes[sum]);
                    rowIndexes.push(rowIndex);
                    stateNodes[next] = previousNodes.length - 1;
                    reachableSums.push(next);
                }
            });

            var total = -1;
            for (var distance = 0; distance <= 5000; distance++) {
                var below = target - distance;
                var above = target + distance;
                if (below >= minimum && below <= maximum && stateNodes[below] !== -2) {
                    total = below;
                    break;
                }
                if (above <= maximum && stateNodes[above] !== -2) {
                    total = above;
                    break;
                }
            }
            if (total < 0) return null;

            var selected = [];
            var node = stateNodes[total];
            while (node >= 0) {
                selected.push(candidateRolls[rowIndexes[node]]);
                node = previousNodes[node];
            }
            return selected;
        }

        function suggestRollsForTarget() {
            var target = targetUnits();
            if (!target || candidateRolls.length === 0) {
                selectedRolls.clear();
                updateCreateButton();
                return;
            }

            var suggested = buildSuggestedRolls(target);
            selectedRolls.clear();
            if (suggested) {
                suggested.forEach(function(row) {
                    selectedRolls.set(String(row.ROLL), Object.assign({}, row, {RQTY: Number(row.AQTY)}));
                });
                syncTargetQtyFromSelection();
            }

            document.querySelectorAll('.roll-select-checkbox').forEach(function(checkbox) {
                var row = candidateRolls[Number(checkbox.dataset.rollIndex)];
                checkbox.checked = Boolean(row && selectedRolls.has(String(row.ROLL)));
                var requiredInput = checkbox.closest('tr').querySelector('.required-qty-input');
                if (requiredInput) {
                    var selectedRow = row ? selectedRolls.get(String(row.ROLL)) : null;
                    requiredInput.disabled = !selectedRow;
                    requiredInput.value = formatQty(selectedRow ? selectedRow.RQTY : row.AQTY);
                }
            });

            updateCreateButton();
            if (!suggested) {
                var status = document.getElementById('resultsStatus');
                status.textContent +=
                    ' No automatic combination is within 5 KG of the target. Select or remove rolls manually.';
            }
        }

        function searchRolls() {
            var poNumber = poNumberSelect.value;
            var color = colorSelect.value;
            hideMsg();
            resetResults(true);
            if (!poNumber || !color) {
                showMsg('Select both PO Number and Color before searching.', 'error');
                return;
            }

            var requestId = searchRequestId;
            searchInProgress = true;
            searchBtn.disabled = true;
            searchBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Searching...';
            document.getElementById('searchResults').classList.remove('is-hidden');
            document.getElementById('resultsStatus').textContent = 'Loading matching rolls...';
            apiGet('search_by_po_color', {po_number: poNumber, color: color}).then(function(response) {
                if (requestId !== searchRequestId) return;
                if (!response.success) throw new Error(response.message || 'Could not search rolls');
                candidateRolls = response.rolls || [];
                renderRolls(candidateRolls);
                setProcessSelectionEnabled(candidateRolls.length > 0);
                if (targetUnits()) {
                    suggestRollsForTarget();
                } else {
                    updateCreateButton();
                }
            }).catch(function(error) {
                if (requestId !== searchRequestId) return;
                document.getElementById('resultsStatus').textContent = '';
                showMsg(error.message, 'error');
            }).then(function() {
                if (requestId !== searchRequestId) return;
                searchInProgress = false;
                searchBtn.disabled = !poNumberSelect.value || !colorSelect.value;
                searchBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Search';
            });
        }

        function toggleRollSelection(row, checked, checkbox) {
            var key = String(row.ROLL);
            var requiredInput = checkbox.closest('tr').querySelector('.required-qty-input');
            if (checked) {
                selectedRolls.set(key, Object.assign({}, row, {RQTY: Number(row.AQTY)}));
                requiredInput.disabled = false;
                requiredInput.value = formatQty(row.AQTY);
                hideMsg();
            } else {
                selectedRolls.delete(key);
                requiredInput.disabled = true;
            }
            syncTargetQtyFromSelection();
            updateCreateButton();
        }

        function renderSelectedRolls() {
            var rows = Array.from(selectedRolls.values());
            var total = rows.reduce(function(sum, row) {
                return sum + qtyUnits(row);
            }, 0);
            var knittingTotal = rows.reduce(function(sum, row) {
                return sum + knittingQtyUnits(row);
            }, 0);
            var availableTotal = rows.reduce(function(sum, row) {
                return sum + availableQtyUnits(row);
            }, 0);
            document.getElementById('suggestionRolls').innerHTML = rows.length
                ? rows.map(function(row) {
                    return '<tr><td><span class="roll-badge">' + esc(row.ROLL) + '</span></td>' +
                        '<td>' + esc(row.RACK) + '</td><td>' + esc(formatQty(row.KQTY)) + '</td>' +
                        '<td>' + esc(formatQty(row.AQTY)) + '</td><td>' + esc(formatQty(row.RQTY)) + '</td>' +
                        '<td><button type="button" class="remove-roll-btn" data-remove-roll="' +
                        esc(row.ROLL) + '"><i class="fa-solid fa-trash"></i> Remove</button></td></tr>';
                }).join('')
                : '<tr class="empty-row"><td colspan="6">Select rolls from the Matching Rolls table above.</td></tr>';
            document.getElementById('selectedRollsFoot').innerHTML =
                '<tr><th colspan="2" class="total-label">Total QTY:</th>' +
                '<th>' + formatQty(knittingTotal / 1000) + ' KG</th>' +
                '<th>' + formatQty(availableTotal / 1000) + ' KG</th>' +
                '<th id="selectedQtyTotal" class="selected-total-value">' + formatQty(total / 1000) + ' KG</th>' +
                '<th></th></tr>';

            document.querySelectorAll('[data-remove-roll]').forEach(function(button) {
                button.addEventListener('click', function() {
                    var key = this.dataset.removeRoll;
                    selectedRolls.delete(key);
                    var matchingCheckbox = Array.from(document.querySelectorAll('.roll-select-checkbox'))
                        .find(function(checkbox) {
                            var row = candidateRolls[Number(checkbox.dataset.rollIndex)];
                            return row && String(row.ROLL) === key;
                        });
                    if (matchingCheckbox) matchingCheckbox.checked = false;
                    if (matchingCheckbox) {
                        var requiredInput = matchingCheckbox.closest('tr').querySelector('.required-qty-input');
                        if (requiredInput) requiredInput.disabled = true;
                    }
                    syncTargetQtyFromSelection();
                    updateCreateButton();
                });
            });
        }

        function confirmCreate() {
            var selected = Array.from(selectedRolls.values());
            var processes = getSelectedProcesses();
            if (!dyeingMcnoSelect.value) {
                showMsg('Select a Dyeing MC No before creating the batch card.', 'error');
                return;
            }
            if (Object.keys(processes).length === 0) {
                showMsg('Select at least one Dyeing Process before creating the batch card.', 'error');
                return;
            }
            if (!selected.length || confirmCreateBtn.disabled) {
                showMsg('Select at least one roll and enter a valid Required QTY.', 'error');
                return;
            }

            confirmCreateBtn.disabled = true;
            confirmCreateBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
            var payload = {
                po_number: poNumberSelect.value,
                color: colorSelect.value,
                target_qty: Number(targetQtyInput.value),
                dymcno: dyeingMcnoSelect.value,
                processes: processes,
                rolls: selected.map(function(row) {
                    return {ROLL: row.ROLL, required_qty: Number(row.RQTY)};
                })
            };

            apiPost('select_rolls', payload).then(function(response) {
                if (!response.success) throw new Error(response.message || 'Could not select suggested rolls');
                return fetch('ajaxDyeing_batch_card_Insert.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({})
                }).then(readJson).then(function(insertResponse) {
                    if (!insertResponse.success) {
                        throw new Error(insertResponse.message || 'Database insert failed. Batch Card was not created.');
                    }
                    return insertResponse;
                });
            }).then(function(response) {
                document.getElementById('cardNo').textContent = response.card_no || '-';
                var successMessage = 'Batch Created Successfully!\n' + response.count +
                    ' roll(s) saved in the database.\nBatch Card No: ' + response.card_no;
                showMsg(successMessage.replace(/\n/g, ' '), 'success');
                showBatchPopup(successMessage.replace(/\n/g, ' '), 'success');
                confirmCreateBtn.innerHTML = '<i class="fa-solid fa-check"></i> Batch Card Created';
            }).catch(function(error) {
                showMsg(error.message, 'error');
                showBatchPopup('Batch Card was NOT created. ' + error.message, 'error');
                confirmCreateBtn.disabled = false;
                confirmCreateBtn.innerHTML = '<i class="fa-solid fa-check"></i> Confirm &amp; Create Batch Card';
            });
        }

        function loadDyeingMachines() {
            dyeingMcnoSelect.disabled = true;
            dyeingMcnoSelect.innerHTML = '<option value="">Loading dyeing machines...</option>';
            apiGet('dyeing_machines').then(function(response) {
                if (!response.success) throw new Error(response.message || 'Could not load dyeing machines');
                dyeingMcnoSelect.innerHTML = '<option value="">Select Dyeing MC No</option>';
                (response.machines || []).forEach(function(machine) {
                    var option = document.createElement('option');
                    option.value = machine.DMCNO;
                    option.textContent = machine.DMCNO + ' | ' + machine.DCAPACITY;
                    dyeingMcnoSelect.appendChild(option);
                });
                dyeingMcnoSelect.disabled = !response.machines || response.machines.length === 0;
                if (!response.machines || response.machines.length === 0) {
                    dyeingMcnoSelect.innerHTML = '<option value="">No dyeing machines found</option>';
                    showMsg('No dyeing machines found in dyeing_mcno.', 'error');
                }
                updateCreateButton();
            }).catch(function(error) {
                dyeingMcnoSelect.innerHTML = '<option value="">Unable to load dyeing machines</option>';
                dyeingMcnoSelect.disabled = true;
                showMsg(error.message, 'error');
            });
        }

        function refreshColorsForCurrentPo() {
            if (poInputTimer) {
                clearTimeout(poInputTimer);
                poInputTimer = null;
            }
            hideMsg();
            loadColors(poNumberSelect.value.trim());
            searchBtn.disabled = true;
        }

        poNumberSelect.addEventListener('input', function() {
            colorRequestId++;
            colorSelect.disabled = true;
            colorSelect.innerHTML = '<option value="">Select a PO Number first</option>';
            searchBtn.disabled = true;
            resetResults();
            if (poInputTimer) clearTimeout(poInputTimer);

            var poNumber = this.value.trim();
            if (!poNumber) return;

            poInputTimer = setTimeout(function() {
                poInputTimer = null;
                loadColors(poNumber);
            }, 300);
        });

        poNumberSelect.addEventListener('change', refreshColorsForCurrentPo);
        poNumberSelect.addEventListener('search', refreshColorsForCurrentPo);

        colorSelect.addEventListener('change', function() {
            if (poNumberSelect.value.trim() && this.value) {
                searchRolls();
            } else {
                resetResults();
                searchBtn.disabled = true;
            }
        });

        searchBtn.addEventListener('click', searchRolls);
        processPanel.querySelectorAll('input[data-process-field]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                if (this.dataset.processField === 'OTHERS') {
                    var othersInput = document.getElementById('othersProcessInput');
                    var enabled = this.checked;
                    othersInput.disabled = !enabled;
                    document.getElementById('othersDetail').classList.toggle('is-hidden', !enabled);
                    if (!enabled) othersInput.value = '';
                }
                updateCreateButton();
            });
        });
        document.getElementById('othersProcessInput').addEventListener('input', updateCreateButton);
        targetQtyInput.addEventListener('input', function() {
            updateCreateButton();
            if (targetQtyTimer) clearTimeout(targetQtyTimer);
            targetQtyTimer = setTimeout(function() {
                targetQtyTimer = null;
                suggestRollsForTarget();
            }, 250);
        });
        targetQtyInput.addEventListener('change', function() {
            if (targetQtyTimer) {
                clearTimeout(targetQtyTimer);
                targetQtyTimer = null;
            }
            suggestRollsForTarget();
        });
        targetQtyInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (targetQtyTimer) {
                    clearTimeout(targetQtyTimer);
                    targetQtyTimer = null;
                }
                if (!poNumberSelect.value.trim() || !colorSelect.value) {
                    showMsg('Select PO Number and Color to show matching rolls.', 'error');
                } else if (searchInProgress) {
                    return;
                } else if (!document.getElementById('searchResults').classList.contains('is-hidden')) {
                    suggestRollsForTarget();
                } else {
                    searchRolls();
                }
            }
        });
        dyeingMcnoSelect.addEventListener('change', updateCreateButton);
        confirmCreateBtn.addEventListener('click', confirmCreate);
        document.getElementById('backBtn').addEventListener('click', function(event) {
            event.preventDefault();
            window.location.href = 'initialPage.php';
        });

        apiGet('get_batch').then(function(response) {
            if (response.success) {
                document.getElementById('cardNo').textContent =
                    response.next_card_no || response.card_no || '-';
            }
        }).catch(function(error) {
            showMsg(error.message, 'error');
        });
        loadPoNumbers();
        loadDyeingMachines();
    </script>
</body>

</html>