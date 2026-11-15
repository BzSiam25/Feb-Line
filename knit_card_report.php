<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit();
}

$search_filter = isset($_GET['search']) && is_scalar($_GET['search']) ? trim((string)$_GET['search']) : '';

// Ensure UID column exists in knit_card (auto-heal for imported DBs)
$uid_col_chk = @$db->query("SHOW COLUMNS FROM knit_card LIKE 'UID'");
if ($uid_col_chk && $uid_col_chk->num_rows === 0) {
    @$db->query("ALTER TABLE knit_card ADD COLUMN UID varchar(100) DEFAULT NULL AFTER UNAME");
}

// Auto-backfill operator in knit_card from knitting_production if UID is missing or empty
@$db->query("
    UPDATE knit_card kc
    JOIN (
        SELECT KNITCARD, UID, UNAME 
        FROM knitting_production 
        WHERE UID IS NOT NULL AND UID != '' 
        GROUP BY KNITCARD
    ) kp ON kc.KNITCARD = kp.KNITCARD
    SET kc.UID = kp.UID,
        kc.UNAME = COALESCE(NULLIF(kp.UNAME, ''), kc.UNAME)
    WHERE kc.UID IS NULL OR kc.UID = ''
");

// Build query using real column names dynamically
$kc_prog_col = get_knit_card_program_col($db);
$query  = "SELECT kc.*, 
                  kp.PO_NUMBER AS kp_booking, 
                  kp.REMARKS AS prog_remarks, 
                  kp.MCDIA AS prog_mcdia, 
                  kp.CATEGORY AS prog_category,
                  (
                      SELECT GROUP_CONCAT(DISTINCT kprod.UNAME SEPARATOR ', ')
                      FROM knitting_production kprod
                      WHERE kprod.KNITCARD = kc.KNITCARD 
                        AND kprod.UNAME IS NOT NULL AND kprod.UNAME != ''
                  ) AS prod_operators
           FROM knit_card kc
           LEFT JOIN knitting_program kp ON kp.KPTID = (
               SELECT kp2.KPTID FROM knitting_program kp2
               WHERE kp2.PROGRAM_NO = kc.{$kc_prog_col} OR kp2.KPTID = kc.{$kc_prog_col}
               ORDER BY (kp2.PROGRAM_NO = kc.{$kc_prog_col}) DESC, kp2.KPTID DESC
               LIMIT 1
           )  -- exactly one program per card, so every knit card shows once
           WHERE 1=1";
$params = [];
$types  = '';

if ($search_filter !== '') {
    $query .= " AND (
        kc.PO_NUMBER LIKE ? OR kc.SONO LIKE ? OR kc.BUYER LIKE ? OR
        kc.STYLE LIKE ? OR kc.CUSTOMER LIKE ? OR kc.KNITCARD LIKE ? OR
        CAST(kc.{$kc_prog_col} AS CHAR) LIKE ? OR kp.PROGRAM_NO LIKE ?
    )";
    $search_pattern = "%{$search_filter}%";
    $params = array_fill(0, 8, $search_pattern);
    $types .= 'ssssssss';
}

$query .= " ORDER BY kc.KCTID DESC";

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

$total_cards = 0;
$total_qty   = 0.00;
$buyers_set  = [];
$mc_set      = [];
$rows_array  = [];

if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        $rows_array[]   = $r;
        $total_cards++;
        $total_qty     += floatval($r['QTY'] ?? 0);
        if (!empty($r['BUYER'])) $buyers_set[$r['BUYER']] = true;
        if (!empty($r['MCNO']))  $mc_set[$r['MCNO']]      = true;
    }
}

$page_rows = $rows_array;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knit Card Report | Purbani Fabrics</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/mycss.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-teal: #00796b;
            --dark-teal: #004d40;
            --accent-green: #059669;
            --surface-bg: #f0f4f9;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            --header-from:  #0a0f2e;
            --header-mid:   #0d2168;
            --header-to:    #1a56db;
        }

        i, i.fa-solid, i.fas, i.far, i.fab {
            border: none !important; outline: none !important; box-shadow: none !important;
            padding: 0 !important; display: inline-block !important; transform: none !important;
        }

        body {
            padding: 20px;
            background-color: var(--surface-bg);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            color: #334155;
        }

        /* ═══════════════════════════════════════════
           HEADER BANNER — Premium Indigo / Violet
        ═══════════════════════════════════════════ */
        .top-banner {
            position: relative;
            background: linear-gradient(135deg, var(--header-from) 0%, var(--header-mid) 45%, var(--header-to) 100%);
            color: white;
            padding: 32px 36px 0 36px;
            border-radius: 22px;
            box-shadow: 0 20px 50px rgba(76, 29, 149, 0.35);
            margin-bottom: 28px;
            overflow: hidden;
        }

        /* Decorative background blobs */
        .top-banner::before {
            content: '';
            position: absolute;
            width: 340px; height: 340px;
            background: radial-gradient(circle, rgba(96, 165, 250, 0.18) 0%, transparent 70%);
            top: -90px; right: -70px;
            border-radius: 50%;
            pointer-events: none;
        }
        .top-banner::after {
            content: '';
            position: absolute;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(147, 197, 253, 0.13) 0%, transparent 70%);
            bottom: 0; left: 50px;
            border-radius: 50%;
            pointer-events: none;
        }

        .banner-inner {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            padding-bottom: 26px;
        }

        /* Icon badge */
        .banner-icon-wrap {
            width: 58px; height: 58px;
            border-radius: 16px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
            backdrop-filter: blur(6px);
        }

        .banner-title-group { display: flex; align-items: center; gap: 18px; }

        .top-banner h1 {
            font-weight: 800;
            font-size: 1.85rem;
            margin: 0;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .banner-subtitle {
            font-size: 13.5px;
            color: rgba(233, 213, 255, 0.85);
            margin: 0;
            font-weight: 400;
            letter-spacing: 0.2px;
        }

        /* Project Standard Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #000000;
            color: #ffffff;
            border: 2px solid #000000;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background .15s, color .15s, transform .2s ease;
            white-space: nowrap;
        }

        .back-btn:hover {
            background: #ffffff;
            color: #000000;
            border-color: #000000;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,.2);
        }

        /* Bottom glassy strip inside header */
        .banner-info-strip {
            position: relative;
            z-index: 2;
            background: rgba(255,255,255,0.07);
            border-top: 1px solid rgba(255,255,255,0.12);
            margin: 0 -36px;
            padding: 12px 36px;
            display: flex;
            align-items: center;
            gap: 28px;
            flex-wrap: wrap;
        }

        .strip-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            color: rgba(186, 230, 253, 0.9);
            font-weight: 500;
        }

        .strip-item .strip-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        /* ═══════════════════════════════════════════
           STAT CARDS
        ═══════════════════════════════════════════ */
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e8edf2;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.25s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px; height: 50px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 21px;
            flex-shrink: 0;
        }
        .stat-icon.navy   { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1d4ed8; }
        .stat-icon.sky    { background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: #0369a1; }
        .stat-icon.royal  { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
        .stat-icon.cobalt { background: linear-gradient(135deg, #eef2ff, #e0e7ff); color: #3730a3; }

        .stat-label { font-size: 11.5px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.6px; margin: 0; }
        .stat-value { font-size: 22px; font-weight: 800; color: #0f172a; margin: 2px 0 0; letter-spacing: -0.5px; }

        /* ═══════════════════════════════════════════
           FILTER PANEL
        ═══════════════════════════════════════════ */
        .filter-panel {
            background: #fff;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 9px 14px;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        }

        /* ═══════════════════════════════════════════
           TABLE
        ═══════════════════════════════════════════ */
        .content-panel {
            background: #fff;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
        }

        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .custom-table thead th {
            position: sticky;   /* header stays visible while scrolling the table */
            top: 0;
            z-index: 5;
            white-space: nowrap;
            background: linear-gradient(135deg, #0a0f2e, #1e3a8a);
            color: #bfdbfe;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 14px 16px;
            border: none;
        }
        .custom-table thead th:first-child { border-top-left-radius: 12px; }
        .custom-table thead th:last-child  { border-top-right-radius: 12px; }
        .custom-table tbody td { padding: 14px 16px; font-size: 13.5px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .custom-table tbody tr:hover { background-color: #eff6ff; }

        .custom-table tbody td,
        .custom-table tbody td strong,
        .custom-table tbody td small,
        .custom-table tbody td span { color: #334155; }

        /* Keep all rows visible while allowing wide tables to scroll horizontally. */
        .content-panel .table-responsive {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
        }
        .custom-table tbody td { white-space: nowrap; }

        /* ═══ TABLE HEAD (title + page number) ═══ */
        .table-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            padding: 0 2px 12px;
            margin-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .table-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }
        .table-title i { color: #2563eb; }
        .table-head-right { display: inline-flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .page-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
            border-radius: 100px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .per-page-form { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #64748b; margin: 0; }
        .per-page-form select { font-size: 12px; padding: 3px 6px; border-radius: 6px; border: 1px solid #cbd5e1; }

        /* ═══ PAGINATION ═══ */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 12px;
            margin-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        .pagination-info { font-size: 12.5px; color: #64748b; font-weight: 600; }
        .custom-pagination { display: inline-flex; gap: 4px; list-style: none; padding: 0; margin: 0; align-items: center; flex-wrap: wrap; }
        .custom-pagination .page-link-custom {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 30px; height: 30px; padding: 0 9px; border-radius: 6px;
            font-size: 12.5px; font-weight: 600; color: #334155;
            background: #f8fafc; border: 1px solid #e2e8f0; text-decoration: none; transition: all 0.2s ease;
        }
        .custom-pagination .page-link-custom:hover { background: #f1f5f9; color: #0f172a; }
        .custom-pagination .page-item-custom.active .page-link-custom { background: #1e3a8a; color: #ffffff; border-color: #1e3a8a; }
        .custom-pagination .page-item-custom.disabled .page-link-custom { color: #94a3b8; cursor: not-allowed; pointer-events: none; opacity: 0.6; }
        .custom-pagination .page-ellipsis { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 30px; color: #94a3b8; font-size: 12px; }

        body {
            padding: clamp(12px, 2vw, 24px);
            background: #f4f7fb;
            color: #334155;
        }
        .report-container { width: 100%; max-width: none; padding: 0; }
        .report-page-header {
            position: relative;
            margin-bottom: 20px;
        }
        .report-page-header .header-spacer { width: 150px; }
        .stat-card {
            min-height: 100px;
            height: 100%;
            padding: 18px 20px;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon { width: 44px; height: 44px; border-radius: 12px; font-size: 18px; }
        .stat-value { font-size: clamp(18px, 2vw, 23px); }
        .filter-panel {
            margin-bottom: 18px;
            padding: 18px 20px;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        }
        .search-label {
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 15px;
            font-weight: 700;
        }
        .search-controls {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 9px;
            align-items: center;
        }
        .search-controls .form-control {
            min-width: 0;
            height: 44px;
            border-radius: 9px;
        }
        .search-submit, .search-clear {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border-radius: 9px;
            padding: 0 18px;
            font-weight: 700;
            white-space: nowrap;
        }
        .search-submit { background: #2563eb; border-color: #2563eb; }
        .search-submit:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .search-clear { border: 1px solid #cbd5e1; color: #475569; }
        .search-clear:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
        .content-panel {
            padding: clamp(14px, 1.8vw, 22px);
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        }
        .table-head { padding-bottom: 12px; }
        .table-title { font-size: 15px; }
        .content-panel .table-responsive {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .custom-table thead th {
            background: #0f172a;
            color: #f8fafc;
            padding: 12px 14px;
        }
        .custom-table tbody td { padding: 11px 14px; }
        @media (max-width: 700px) {
            .report-page-header { margin-bottom: 16px; }
            .report-page-header .header-spacer { width: 1px; }
            .stat-card { min-height: 82px; padding: 12px; gap: 10px; }
            .stat-icon { width: 38px; height: 38px; font-size: 16px; }
            .stat-value { font-size: 18px; }
            .filter-panel { padding: 14px; }
            .search-controls { grid-template-columns: minmax(0, 1fr) auto; }
            .search-controls .form-control { grid-column: 1 / -1; }
            .search-submit, .search-clear { width: 100%; padding: 0 10px; }
            .content-panel { padding: 12px; }
            .table-head { align-items: flex-start; }
            .table-head-right { justify-content: flex-start; }
            .pagination-container { align-items: flex-start; }
        }
        @media (max-width: 380px) {
            .search-controls { grid-template-columns: 1fr 1fr; }
            .search-controls .form-control { grid-column: 1 / -1; }
        }
    </style>
    <link rel="stylesheet" href="css/knitting-report-theme.css">
</head>

<body>

    <div class="container-fluid report-container">

        <div class="report-page-header knitting-report-header">
            <a href="report.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Report
            </a>
            <h1>Knit Card Report</h1>
            <div class="header-spacer" aria-hidden="true"></div>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon navy"><i class="fa-solid fa-file-invoice"></i></div>
                    <div>
                        <p class="stat-label">Total Cards</p>
                        <p class="stat-value"><?php echo $total_cards; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon sky"><i class="fa-solid fa-weight-hanging"></i></div>
                    <div>
                        <p class="stat-label">Total Quantity</p>
                        <p class="stat-value"><?php echo number_format($total_qty, 2); ?> <small style="font-size:13px;font-weight:500;color:#94a3b8;">KG</small></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon royal"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <p class="stat-label">Active Buyers</p>
                        <p class="stat-value"><?php echo count($buyers_set); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon cobalt"><i class="fa-solid fa-gears"></i></div>
                    <div>
                        <p class="stat-label">Active Machines</p>
                        <p class="stat-value"><?php echo count($mc_set); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-panel search-panel">
            <form method="get" class="single-search-form">
                <label for="reportSearch" class="search-label">Search PO / SO / Buyer / Style</label>
                <div class="search-controls">
                    <input id="reportSearch" type="text" name="search" class="form-control"
                           placeholder="Enter PO No, SONO, Buyer, Style, Knit Card No..."
                           value="<?php echo htmlspecialchars($search_filter); ?>">
                    <button type="submit" class="btn btn-primary search-submit">
                        <i class="fa-solid fa-magnifying-glass"></i> Search
                    </button>
                    <a href="knit_card_report.php" class="btn btn-light search-clear">
                        <i class="fa-solid fa-rotate-left"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="content-panel">
            <div class="table-head">
                <div class="table-title">
                    <i class="fa-solid fa-table-list"></i> Knit Card List
                </div>
            </div>
            <div class="table-responsive">
                <table class="table custom-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">Print</th>
                            <th>Card Date</th>
                            <th>Knit Card</th>
                            <th>PO NO</th>
                            <th>Color</th>
                            <th>QTY (KG)</th>
                            <th>SONO</th>
                            <th>Buyer</th>
                            <th>Customer</th>
                            <th>Style</th>
                            <th class="text-nowrap">Category</th>
                            <th>Shift</th>
                            <th>M/C No</th>
                            <th>M/C Dia</th>
                            <th>Fabric Type</th>
                            <th>GSM</th>
                            <th>Finish Dia</th>
                            <th>Yarn Type</th>
                            <th>Yarn Count</th>
                            <th>Yarn Brand</th>
                            <th>Lot No</th>
                            <th>Remarks</th>
                            <th>Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($page_rows) > 0): ?>
                            <?php foreach ($page_rows as $row): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="d-inline-flex justify-content-center">
                                            <a href="knit_card_view.php?id=<?php echo intval($row['KCTID']); ?>&print=1&from=report"
                                               onclick="return printKnitCard(<?php echo intval($row['KCTID']); ?>);"
                                               class="btn btn-sm px-3 py-1"
                                               style="border-radius:8px; font-size:12.5px; background:#0f172a; color:#ffffff !important; border:none;"
                                               title="Print this Knit Card">
                                                <i class="fa-solid fa-print me-1" style="color:#ffffff !important;"></i> <span style="color:#ffffff !important;">Print</span>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $card_date = trim((string)($row['CREATED_DATE'] ?? ''));
                                        $card_timestamp = $card_date !== '' ? strtotime($card_date) : false;
                                        echo htmlspecialchars($card_timestamp !== false ? date('d-m-Y', $card_timestamp) : $card_date);
                                        ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars(!empty($row['KNITCARD']) ? $row['KNITCARD'] : ('KC-' . $row['KCTID'])); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($row['PO_NUMBER'] ?? ''); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['COLOR'] ?? ''); ?></td>
                                    <td><strong><?php echo number_format((float)($row['QTY'] ?? 0), 2); ?> KG</strong></td>
                                    <td><?php echo htmlspecialchars($row['SONO'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['BUYER'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['CUSTOMER'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['STYLE'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(!empty($row['CATEGORY']) ? $row['CATEGORY'] : ($row['prog_category'] ?? 'N/A')); ?></td>
                                    <td><?php echo htmlspecialchars($row['SHIFT'] ?? ''); ?></td>
                                    <td>M/C <?php echo htmlspecialchars($row['MCNO'] ?? ''); ?></td>
                                    <td><strong><?php echo htmlspecialchars(!empty($row['MCDIA']) ? $row['MCDIA'] : ($row['prog_mcdia'] ?? 'N/A')); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['FTYPE'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['FGSM'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['FDIA'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['YTYPE'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['YCOUNT'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['YBRAND'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['LOT'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['prog_remarks'] ?? ($row['REMARKS'] ?? '')); ?></td>
                                    <td>
                                        <?php
                                        $op_name  = trim((string)($row['UNAME'] ?? ''));
                                        $prod_ops = trim((string)($row['prod_operators'] ?? ''));

                                        $operator_name = $op_name !== '' && strcasecmp($op_name, (string)($row['UID'] ?? '')) !== 0
                                            ? $op_name
                                            : $prod_ops;
                                        if ($operator_name !== '') {
                                            echo htmlspecialchars($operator_name);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="23" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fa-3x mb-3 text-secondary d-block"></i>
                                    <h6 class="fw-bold">No Knit Cards Found</h6>
                                    <p class="small mb-0">Try clearing your search filters or generate a new card from the Knitting Programs page.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

    <iframe id="knitCardPrintFrame" title="Knit card print" aria-hidden="true"
            style="position:fixed;right:0;bottom:0;width:0;height:0;border:0;"></iframe>

    <script src="jquery.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        function printKnitCard(cardId) {
            var frame = document.getElementById('knitCardPrintFrame');
            frame.src = 'knit_card_view.php?id=' + encodeURIComponent(cardId) + '&print=1&from=report';
            return false;
        }
    </script>
</body>
</html>
