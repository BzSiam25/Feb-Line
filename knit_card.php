<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit();
}

$knit_card_generation_success = $_SESSION['knit_card_generation_success'] ?? '';
unset($_SESSION['knit_card_generation_success']);

// ── AJAX: VERIFY KNITTING PRODUCTION OPERATOR (QR / ID) ──
if (isset($_GET['action']) && $_GET['action'] === 'verify_operator') {
    header('Content-Type: application/json');
    $op_input = trim($_GET['operator_id'] ?? $_GET['query'] ?? '');

    if ($op_input === '') {
        echo json_encode(['success' => false, 'error' => 'Operator ID is required']);
        exit();
    }

    // Only registered Knit Card Production Operators may access the Knit Card section
    $op_stmt = $db->prepare("SELECT OPERATOR_ID, OPERATOR_NAME FROM knitting_operator
                             WHERE LOWER(TRIM(OPERATOR_ID)) = LOWER(TRIM(?))
                                OR LOWER(TRIM(OPERATOR_NAME)) = LOWER(TRIM(?))
                             LIMIT 1");
    if ($op_stmt) {
        $op_stmt->bind_param("ss", $op_input, $op_input);
        if ($op_stmt->execute()) {
            $op_res = $op_stmt->get_result();
            if ($op_res && $op_row = $op_res->fetch_assoc()) {
                $_SESSION['knit_card_operator'] = [
                    'id'   => $op_row['OPERATOR_ID'],
                    'name' => $op_row['OPERATOR_NAME'],
                    'role' => 'production'
                ];
                echo json_encode([
                    'success' => true,
                    'data'    => [
                        'OPERATOR_ID'   => $op_row['OPERATOR_ID'],
                        'OPERATOR_NAME' => $op_row['OPERATOR_NAME'],
                        'ROLE'          => 'Knit Card Production Operator'
                    ]
                ]);
                $op_stmt->close();
                exit();
            }
        }
        $op_stmt->close();
    }

    // Informative denial when a QC operator tries to use this page
    $qc_stmt = $db->prepare("SELECT KNITTING_QC_ID, KNITTING_QC_NAME FROM knitting_operator_qc
                             WHERE LOWER(TRIM(KNITTING_QC_ID)) = LOWER(TRIM(?))
                                OR LOWER(TRIM(KNITTING_QC_NAME)) = LOWER(TRIM(?))
                             LIMIT 1");
    if ($qc_stmt) {
        $qc_stmt->bind_param("ss", $op_input, $op_input);
        $qc_stmt->execute();
        $qc_res = $qc_stmt->get_result();
        if ($qc_res && $qc_row = $qc_res->fetch_assoc()) {
            $qc_stmt->close();
            echo json_encode(['success' => false, 'error' => 'Not Found Operator']);
            exit();
        }
        $qc_stmt->close();
    }

    echo json_encode(['success' => false, 'error' => 'Not Found Operator']);
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'logout_operator') {
    header('Content-Type: application/json');
    unset($_SESSION['knit_card_operator']);
    echo json_encode(['success' => true]);
    exit();
}

// ── Require a fresh operator scan on every new entry to the Knit Card section ──
// The verified operator is kept only for in-page navigation (search).
// and the immediate post-verification reload. Arriving from any other page
// (e.g. the Dashboard) clears it, forcing another scan / ID entry.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !isset($_GET['action'])) {
    $ref_path = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '';
    $ref_base = strtolower(basename($ref_path));
    $allowed_referrers = ['knit_card.php', 'knit_card_generate.php', 'knit_card_view.php', 'knit_card_print.php'];
    if (!in_array($ref_base, $allowed_referrers, true)) {
        unset($_SESSION['knit_card_operator']);
    }
}

$knit_card_operator = $_SESSION['knit_card_operator'] ?? null;

// ── ACCESS GATE: require a scanned / typed Knit Card Production Operator ID ──
if (empty($knit_card_operator) || empty($knit_card_operator['id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Knit Card | Operator Login</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
                   background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
                   font-family: 'Segoe UI', Roboto, system-ui, -apple-system, sans-serif; }
            .gate-card { width: 100%; max-width: 420px; background: #ffffff; border-radius: 18px; padding: 22px 22px 20px;
                         box-shadow: 0 24px 60px rgba(0,0,0,0.35); }
            .gate-head { text-align: center; margin-bottom: 14px; }
            .gate-icon { width: 54px; height: 54px; border-radius: 14px; background: #eff6ff; color: #2563eb;
                         display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 10px; }
            .gate-title { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; }
            .gate-sub { font-size: 12px; color: #64748b; margin: 4px 0 0; }
            #qr-reader { width: 100%; border-radius: 12px; overflow: hidden; background: #0f172a; min-height: 220px; }
            #qr-reader video { width: 100% !important; height: auto !important; }
            .cam-status { text-align: center; font-size: 12px; font-weight: 600; color: #0369a1; margin: 8px 0 12px; }
            .manual { display: flex; gap: 8px; }
            .manual input { flex: 1; height: 42px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; font-size: 14px; outline: none; }
            .manual input:focus { border-color: #2563eb; }
            .manual button { height: 42px; padding: 0 16px; border: none; border-radius: 10px; background: linear-gradient(135deg,#10b981,#059669);
                             color: #ffffff; font-weight: 700; font-size: 13px; cursor: pointer; white-space: nowrap; }
            .manual button:active { transform: scale(0.98); }
            .gate-actions { display: flex; justify-content: center; gap: 8px; margin-top: 8px; }
            .gate-actions button { border-radius: 20px; padding: 5px 14px; font-size: 12px; font-weight: 600; cursor: pointer;
                                   transition: background 0.2s ease, transform 0.1s ease; }
            #camSwitchBtn { background: #2563eb; border: 1px solid #1d4ed8; color: #ffffff; }
            #camSwitchBtn:hover { background: #1d4ed8; }
            #pageRefreshBtn { background: #0f766e; border: 1px solid #115e59; color: #ffffff; }
            #pageRefreshBtn:hover { background: #115e59; }
            .gate-actions button:active { transform: scale(0.97); }
            #opStatusMsg { margin-top: 12px; font-size: 12.5px; text-align: center; }
            .gate-back {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                margin-top: 16px;
                padding: 10px 18px;
                font-size: 13px;
                font-weight: 700;
                background: #000000 !important;
                color: #ffffff !important;
                border: 2px solid #000000 !important;
                border-radius: 8px;
                text-decoration: none;
                transition: 0.25s ease;
                box-sizing: border-box;
            }
            .gate-back:hover {
                background: #ffffff !important;
                color: #000000 !important;
                border-color: #000000 !important;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="gate-card">
            <div class="gate-head">
                <div class="gate-icon"><i class="fa-solid fa-user-gear"></i></div>
                <h1 class="gate-title">Knit Card Production Operator</h1>
            </div>
            <div id="qr-reader"></div>
            <div class="cam-status" id="camera-status">Starting camera&hellip;</div>
            <div class="manual">
                <input type="text" id="opInput" placeholder="Enter / Scan Operator ID" autocomplete="off" autocapitalize="off">
                <button type="button" id="opBtn">Authenticate</button>
            </div>
            <div class="gate-actions">
                <button type="button" id="camSwitchBtn"><i class="fa-solid fa-sync-alt"></i> Switch Camera</button>
                <button type="button" id="pageRefreshBtn"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
            </div>
            <div id="opStatusMsg"></div>
            <a class="gate-back" href="initialPage.php"><i class="fa-solid fa-arrow-left"></i> Back to Initial Page</a>
        </div>
        <script>
        (function () {
            var html5QrCode = null, verifying = false, errorOpen = false, facingMode = 'environment';
            var lastText = '', lastTime = 0;
            var rejectedText = '', rejectedResetTimer = null;
            var statusEl = document.getElementById('camera-status');
            var msgDiv   = document.getElementById('opStatusMsg');
            var input    = document.getElementById('opInput');

            function setMsg(html, ok) {
                if (msgDiv) msgDiv.innerHTML = '<div style="color:' + (ok ? '#0369a1' : '#dc2626') + ';font-weight:700;">' + html + '</div>';
            }

            function showOperatorError(message, rejectedValue) {
                verifying = false;
                errorOpen = true;
                if (rejectedValue) {
                    rejectedText = rejectedValue;
                    if (rejectedResetTimer) clearTimeout(rejectedResetTimer);
                    rejectedResetTimer = null;
                }
                if (msgDiv) msgDiv.innerHTML = '';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: message,
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(function () {
                        errorOpen = false;
                    });
                } else {
                    setMsg('<i class="fa-solid fa-triangle-exclamation"></i> ' + message, false);
                    setTimeout(function () {
                        if (msgDiv) msgDiv.innerHTML = '';
                        errorOpen = false;
                    }, 3000);
                }
            }

            function startScanner() {
                if (statusEl) statusEl.textContent = 'Starting camera\u2026';
                var box = document.getElementById('qr-reader');
                if (!box) return;
                if (html5QrCode) {
                    try {
                        html5QrCode.stop().then(function () { try { html5QrCode.clear(); } catch (e) {} html5QrCode = null; initScanner(); })
                                          .catch(function () { html5QrCode = null; initScanner(); });
                        return;
                    } catch (e) { html5QrCode = null; }
                }
                initScanner();
            }

            function initScanner() {
                var box = document.getElementById('qr-reader');
                box.innerHTML = '';
                try {
                    html5QrCode = new Html5Qrcode('qr-reader');
                    html5QrCode.start(
                        { facingMode: facingMode },
                        { fps: 30, qrbox: function (vw, vh) { var e = Math.min(240, Math.floor(Math.min(vw, vh) * 0.7)); return { width: e, height: e }; }, aspectRatio: 1.0 },
                        onScan,
                        function () {}
                    ).then(function () {
                        if (statusEl) statusEl.textContent = 'Scanning (' + (facingMode === 'environment' ? 'Rear' : 'Front') + ')';
                    }).catch(function (err) {
                        console.warn('Camera start failed:', err);
                        if (statusEl) statusEl.textContent = 'Camera unavailable \u2014 type your ID below';
                    });
                } catch (e) {
                    if (statusEl) statusEl.textContent = 'Camera unavailable \u2014 type your ID below';
                }
            }

            function onScan(text) {
                var now = Date.now();
                text = String(text || '').trim().replace(/[\r\n]+/g, '');
                if (errorOpen) return;
                if (rejectedText && text === rejectedText) {
                    if (rejectedResetTimer) clearTimeout(rejectedResetTimer);
                    rejectedResetTimer = setTimeout(function () {
                        rejectedText = '';
                        rejectedResetTimer = null;
                    }, 1500);
                    return;
                }
                if (rejectedText) {
                    rejectedText = '';
                    if (rejectedResetTimer) clearTimeout(rejectedResetTimer);
                    rejectedResetTimer = null;
                }
                if (!text || (text === lastText && now - lastTime < 1500)) return;
                lastText = text; lastTime = now;
                if (verifying) return;
                submitOperator(text);
            }

            function submitOperator(val) {
                val = String(val || '').trim();
                if (!val) { showOperatorError('Enter Operator ID'); return; }
                verifying = true;
                setMsg('<i class="fa-solid fa-spinner fa-spin"></i> Verifying Operator ID\u2026', true);
                fetch('knit_card.php?action=verify_operator&operator_id=' + encodeURIComponent(val), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.text(); })
                .then(function (t) {
                    var json;
                    try { json = JSON.parse(t.replace(/^\uFEFF/, '').trim()); } catch (e) { throw new Error('Invalid server response'); }
                    if (json.success) {
                        setMsg('<i class="fa-solid fa-circle-check"></i> Welcome ' + ((json.data && json.data.OPERATOR_NAME) || '') + '! Opening Knit Card\u2026', true);
                        stopAndGo();
                    } else {
                        showOperatorError('Not Found Operator', val);
                    }
                })
                .catch(function () { showOperatorError('Unable to verify Operator', val); });
            }

            function stopAndGo() {
                if (html5QrCode) {
                    try {
                        html5QrCode.stop().then(function () { window.location.href = 'knit_card.php'; })
                                          .catch(function () { window.location.href = 'knit_card.php'; });
                        return;
                    } catch (e) {}
                }
                window.location.href = 'knit_card.php';
            }

            document.getElementById('opBtn').addEventListener('click', function () { submitOperator(input.value); });
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); submitOperator(input.value); } });
            document.getElementById('camSwitchBtn').addEventListener('click', function () {
                facingMode = (facingMode === 'environment') ? 'user' : 'environment';
                startScanner();
            });
            document.getElementById('pageRefreshBtn').addEventListener('click', function () {
                window.location.reload();
            });
            window.addEventListener('pagehide', function () { if (html5QrCode) { try { html5QrCode.stop(); } catch (e) {} } });

            var generationSuccessMessage = <?php echo json_encode($knit_card_generation_success, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            if (generationSuccessMessage) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: generationSuccessMessage,
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(function () {
                        window.location.replace('knit_card.php');
                    });
                } else {
                    setMsg(generationSuccessMessage, true);
                    setTimeout(function () {
                        window.location.replace('knit_card.php');
                    }, 3000);
                }
            } else {
                startScanner();
            }
        })();
        </script>
    </body>
    </html>
    <?php
    exit();
}

if (isset($_GET['generate_program_id']) || isset($_POST['generate_program_id'])) {
    $generate_program_id = intval($_GET['generate_program_id'] ?? $_POST['generate_program_id']);
    $_GET['program_id'] = $generate_program_id;
    $is_embedded_knit_card_generation = true;
    include __DIR__ . '/knit_card_generate.php';
    exit();
}

$uname = $_SESSION['username'];

// Search by knitting program or purchase order number
$search_program = isset($_GET['program_id']) ? trim($_GET['program_id']) : (isset($_GET['search']) ? trim($_GET['search']) : '');
$search_term    = ltrim($search_program, '#');

// Build query calculating total carded quantity per program dynamically
$kc_prog_col = get_knit_card_program_col($db);
$query = "SELECT kp.*, 
                 MAX(kc.KCTID) AS card_id, 
                 COALESCE(SUM(kc.QTY), 0) AS total_carded_qty, 
                 MAX(kc.MCNO) AS card_mcno,
                 MAX(kc.MCDIA) AS card_mcdia
          FROM knitting_program kp
          LEFT JOIN knit_card kc ON (kp.PROGRAM_NO = kc.{$kc_prog_col} OR kp.KPTID = kc.{$kc_prog_col})
          WHERE 1=1";
$params = [];
$types  = '';

if ($search_term !== '') {
    $query   .= " AND (kp.KPTID LIKE ? OR kp.PROGRAM_NO LIKE ? OR kp.PO_NUMBER LIKE ? OR kp.SONO LIKE ? OR kp.BUYER LIKE ? OR kp.STYLE LIKE ? OR kp.YBRAND LIKE ?)";
    $like_val = "%{$search_term}%";
    $params[] = $like_val;
    $params[] = $like_val;
    $params[] = $like_val;
    $params[] = $like_val;
    $params[] = $like_val;
    $params[] = $like_val;
    $params[] = $like_val;
    $types   .= 'sssssss';
}

$query .= " GROUP BY kp.KPTID ORDER BY kp.KPTID DESC";

$stmt = $db->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}

// Collect all matching rows
$all_rows = [];

if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        $all_rows[] = $r;
    }
}

$rows_array = $all_rows;
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knitting | Knit Card</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/mycss.css">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-teal: #0f172a;
            --dark-teal: #0f172a;
            --accent-green: #10b981;
            --surface-bg: #f8fafc;
            --card-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
            --header-from:  #0f766e;
            --header-mid:   #0d8a80;
            --header-to:    #0f9b8e;
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }

        i, i.fa-solid, i.fas, i.far, i.fab, i.fa-regular {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
            display: inline-block !important;
            transform: none !important;
        }

        body {
            padding: 10px 14px;
            background-color: var(--surface-bg);
            font-family: var(--font-main);
            color: #334155;
        }

        .top-banner {
            position: relative;
            background: linear-gradient(135deg, var(--header-from) 0%, var(--header-mid) 50%, var(--header-to) 100%);
            color: white;
            padding: 12px 18px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
            margin-bottom: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .top-banner::before {
            content: '';
            position: absolute;
            width: 250px; height: 250px;
            background: radial-gradient(circle, rgba(94, 234, 212, 0.2) 0%, transparent 70%);
            top: -60px; right: -40px;
            border-radius: 50%;
            pointer-events: none;
        }

        .banner-top-row {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            align-items: center;
            gap: 12px;
        }

        .banner-top-row > :first-child {
            justify-self: start;
        }

        .banner-top-row > :last-child {
            justify-self: end;
        }

        .top-banner h1 {
            font-weight: 800;
            font-size: 2rem !important;
            margin: 0;
            text-align: center;
            letter-spacing: -0.3px;
            line-height: 1.2;
            color: #ffffff;
            -webkit-text-fill-color: #ffffff;
        }

        .operator-row {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: flex-start;
            margin-top: 10px;
        }

        .header-action {
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            padding: 5px 12px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .operator-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.18);
            border: 1px solid rgba(16, 185, 129, 0.5);
            color: #d1fae5;
            border-radius: 100px;
            padding: 4px 12px;
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .btn-back {
            background: #000000 !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            transition: all 0.2s ease;
        }
        .btn-back:hover {
            background: #ffffff !important;
            border-color: #000000 !important;
            color: #000000 !important;
        }

        /* ═══════════════════════════════════════════
           SEARCH & FILTER PANEL
        ═══════════════════════════════════════════ */
        .search-panel {
            background: #ffffff;
            border-radius: 10px;
            padding: 8px 12px !important;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            margin-bottom: 12px;
        }

        .search-panel .form-control {
            height: 34px !important;
            padding: 4px 12px !important;
            font-size: 12.5px !important;
            border-color: #cbd5e1;
            border-radius: 0 8px 8px 0 !important;
            background-color: #f8fafc;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }

        .search-form .input-group {
            flex: 0 0 33.333%;
            min-width: 0;
        }

        .search-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }
        
        .search-panel .btn {
            height: 34px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 8px !important;
            font-weight: 600;
            font-size: 12px;
            padding: 0 14px !important;
        }

        .btn-reset {
            background: #64748b !important;
            border: 1px solid #475569 !important;
            color: #ffffff !important;
        }

        .btn-reset:hover {
            background: #475569 !important;
            border-color: #334155 !important;
            color: #ffffff !important;
        }

        @media (min-width: 521px) and (max-width: 991px) {
            .search-form .input-group { flex-basis: 50%; }
        }

        @media (max-width: 520px) {
            body { padding: 8px; }
            .container-fluid { padding-left: 4px !important; padding-right: 4px !important; }
            .top-banner { padding: 10px 12px; }
            .banner-top-row { grid-template-columns: 1fr auto 1fr; gap: 6px; }
            .banner-top-row .header-action { padding: 5px 8px; font-size: 11px; }
            .top-banner h1 { font-size: 1.2rem !important; }
            .operator-row { margin-top: 8px; }
            .search-form { gap: 5px; }
            .search-form .input-group { flex: 1 1 auto; }
            .search-actions { gap: 5px; }
            .search-panel .btn { padding: 0 8px !important; font-size: 11px; }
            .search-panel .input-group-text { padding: 0 8px; }
            .search-panel .form-control { padding: 4px 8px !important; }
        }

        /* ═══════════════════════════════════════════
           TABLES
        ═══════════════════════════════════════════ */
        .table-panel {
            background: #ffffff;
            border-radius: 10px;
            padding: 10px 12px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .custom-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #0f172a;
            color: #f8fafc;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            border: none;
            border-bottom: 2px solid #1e293b;
            text-align: center;
        }
        .custom-table thead th:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
        .custom-table thead th:last-child  { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
        
        .custom-table tbody td { 
            padding: 6px 8px; 
            font-size: 12px; 
            vertical-align: middle; 
            border-bottom: 1px solid #f1f5f9; 
            color: #334155;
            font-weight: 500;
            text-align: center;
        }
        .custom-table tbody tr:hover { 
            background-color: #f8fafc; 
        }

        .badge-status {
            font-size: 10.5px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .badge-generated { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-pending   { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        .btn-teal {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 6px !important;
            padding: 4px 10px !important;
            font-size: 11.5px !important;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2);
            transition: all 0.2s ease;
        }
        .btn-teal:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }

        .btn-action-view {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white !important;
            font-weight: 600;
            border-radius: 6px !important;
            padding: 4px 10px !important;
            border: none;
            font-size: 11.5px !important;
            transition: all 0.2s ease;
        }
        .btn-action-view:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        }

        .table-responsive {
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
        }

        /* ═══════════════════════════════════════════
           TABLE HEADER (STICKY TITLE + PAGE NUMBER)
        ═══════════════════════════════════════════ */
        .table-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            padding: 2px 2px 10px;
            margin-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .table-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
        .table-title i { color: #2563eb; }

        .row-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .row-actions .btn {
            width: 112px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="container-fluid w-100 px-3">

        <!-- ═══ HEADER BANNER ═══ -->
        <div class="top-banner">
            <div class="banner-top-row">
                <a href="initialPage.php" class="btn header-action btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <h1 style="font-weight: bold">Knitting Knit Card</h1>
                <button type="button" class="btn header-action btn-back" id="dashboardRefreshBtn">
                    <i class="fa-solid fa-rotate-right"></i> Refresh
                </button>
            </div>
            <div class="operator-row">
                <span class="operator-badge">
                    <i class="fa-solid fa-user-gear"></i> <?php echo htmlspecialchars($knit_card_operator['name']); ?> (<?php echo htmlspecialchars($knit_card_operator['id']); ?>)
                </span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4 p-3" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 p-3" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <div class="search-panel">
            <form method="GET" action="knit_card.php" class="search-form">
                <div class="input-group">
                    <input type="text" 
                           name="program_id" 
                           class="form-control border-start-0" 
                           placeholder="Search Program No or PO No..."
                           value="<?php echo htmlspecialchars($search_program); ?>"
                           >
                </div>
                <div class="search-actions">
                    <button type="submit" class="btn btn-teal" style="white-space: nowrap;">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                    </button>
                    <a href="knit_card.php" class="btn btn-reset" style="white-space: nowrap;">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Data Table -->
        <div class="table-panel">
            <div class="table-head">
                <div class="table-title">
                    <i class="fa-solid fa-table-list"></i> Knitting Program List
                </div>
            </div>
            <div class="table-responsive">
                <table class="table custom-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center text-nowrap">Actions</th>
                            <th class="text-nowrap">Card Status</th>
                            <th class="text-nowrap">Date</th>
                            <th class="text-nowrap">Program No</th>
                            <th class="text-nowrap">PO NO</th>
                            <th class="text-nowrap">Qty (KG)</th>
                            <th class="text-nowrap">SONO</th>
                            <th class="text-nowrap">Buyer</th>
                            <th class="text-nowrap">Customer</th>
                            <th class="text-nowrap">Style</th>
                            <th class="text-nowrap">Color</th>
                            <th class="text-nowrap">Fabrics Type</th>
                            <th class="text-nowrap">Yarn Type</th>
                            <th class="text-nowrap">Yarn Count</th>
                            <th class="text-nowrap">Brand</th>
                            <th class="text-nowrap">Lot No</th>
                            <th class="text-nowrap">O/T</th>
                            <th class="text-nowrap">M/C Dia</th>
                            <th class="text-nowrap">Finish Dia</th>
                            <th class="text-nowrap">GSM</th>
                            <th class="text-nowrap">SL/VDQ</th>
                            <th class="text-nowrap">Feeder Plan</th>
                            <th class="text-nowrap">Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows_array) > 0): ?>
                            <?php foreach ($rows_array as $row):
                                $p_id          = intval($row['KPTID']);
                                $p_date        = !empty($row['CREATED_DATE']) ? date('Y-m-d', strtotime($row['CREATED_DATE'])) : '';
                                $p_prog        = !empty($row['PROGRAM_NO']) ? $row['PROGRAM_NO'] : (!empty($row['SUB_TID']) ? $row['SUB_TID'] : ($row['KPTID'] ?? ''));
                                $p_po          = $row['PO_NUMBER']   ?? '';
                                $p_sono        = $row['SONO']        ?? '';
                                $p_buyer       = $row['BUYER']       ?? '';
                                $p_style       = $row['STYLE']       ?? '';
                                $p_color       = $row['COLOR']       ?? '';
                                $p_customer    = $row['CUSTOMER']    ?? '';
                                $p_ftype       = $row['FTYPE']       ?? '';
                                $p_ytype       = $row['YTYPE']       ?? '';
                                $p_ycount      = $row['YCOUNT']      ?? '';
                                $p_ybrand      = $row['YBRAND']      ?? '';
                                $p_lot         = $row['LOT']         ?? '';
                                $p_ot          = $row['O_T']         ?? '';
                                $p_mcdia       = !empty($row['MCDIA']) ? $row['MCDIA'] : (!empty($row['card_mcdia']) ? $row['card_mcdia'] : '');
                                $p_fdia        = $row['FDIA']        ?? '';
                                $p_sl          = $row['SL']          ?? '';
                                $p_fgsm        = $row['FGSM']        ?? '';
                                $p_feeder_plan = $row['FEEDER_PLAN'] ?? '';
                                $p_category    = $row['CATEGORY']    ?? '';
                                $p_card_id     = $row['card_id'] ?? '';
                                $prog_total_qty = floatval($row['QTY'] ?? 0);
                                $prog_carded    = floatval($row['total_carded_qty'] ?? 0);
                                $prog_rem       = max(0.00, $prog_total_qty - $prog_carded);
                            ?>
                                <tr>
                                    <td class="text-center text-nowrap">
                                        <div class="row-actions">
                                            <?php if ($prog_rem > 0.001): ?>
                                                <a href="knit_card.php?generate_program_id=<?php echo $p_id; ?>"
                                                   class="btn btn-sm btn-teal"
                                                   style="border-radius:10px; font-size:12.5px;"
                                                   title="Generate Knit Card (Remaining: <?php echo number_format($prog_rem, 2); ?> KG)">
                                                    <i class="fa-solid fa-file-circle-plus me-1"></i> Generate Card
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!empty($p_card_id)): ?>
                                                <a href="knit_card_view.php?id=<?php echo intval($p_card_id); ?>&from=card"
                                                   class="btn btn-sm btn-action-view"
                                                   title="View Latest Generated Card">
                                                    <i class="fa-solid fa-eye me-1"></i> View Card
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($prog_carded <= 0): ?>
                                            <span class="badge-status badge-pending"><i class="fa-solid fa-clock"></i> Pending</span>
                                        <?php elseif ($prog_rem > 0.001): ?>
                                            <span class="badge-status" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd;"><i class="fa-solid fa-spinner"></i> Partial</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-generated"><i class="fa-solid fa-circle-check"></i> Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <i class="fa-regular fa-calendar me-1 text-muted"></i>
                                        <?php echo htmlspecialchars($p_date); ?>
                                    </td>
                                    <td class="text-nowrap"><strong><?php echo htmlspecialchars($p_prog); ?></strong></td>
                                    <td class="text-nowrap"><strong><?php echo htmlspecialchars($p_po ?: 'N/A'); ?></strong></td>
                                    <td class="text-nowrap">
                                        <div style="line-height:1.6; font-size:12.5px;">
                                            <div><span style="color:#64748b; font-weight:600;">Req:</span> <strong><?php echo number_format($prog_total_qty, 0); ?> KG</strong></div>
                                            <div><span style="color:#059669; font-weight:600;">Carded:</span> <strong style="color:#059669;"><?php echo number_format($prog_carded, 0); ?> KG</strong></div>
                                            <div><span style="color:#d97706; font-weight:600;">Rem:</span> <strong style="color:#d97706;"><?php echo number_format($prog_rem, 0); ?> KG</strong></div>
                                        </div>
                                    </td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_sono ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><strong><?php echo htmlspecialchars($p_buyer ?: 'N/A'); ?></strong></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_customer ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_style ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_color ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_ftype ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_ytype ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_ycount ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_ybrand ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_lot ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_ot ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_mcdia ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_fdia ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_fgsm ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_sl ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_feeder_plan ?: 'N/A'); ?></td>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($p_category ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="23" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-3x mb-3 text-secondary d-block"></i>
                                    <h6 class="fw-bold">No Knitting Programs Found</h6>
                                    <p class="small mb-0">Try adjusting your filters or click "New Program" to add an entry.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script src="jquery.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('dashboardRefreshBtn').addEventListener('click', function () {
            var button = this;
            button.disabled = true;
            fetch('knit_card.php?action=logout_operator', { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) throw new Error('Unable to return to operator scan.');
                    return response.json();
                })
                .then(function (result) {
                    if (!result.success) throw new Error('Unable to return to operator scan.');
                    window.location.href = 'knit_card.php';
                })
                .catch(function (error) {
                    button.disabled = false;
                    window.alert(error.message);
                });
        });
    </script>
</body>

</html>
