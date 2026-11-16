<?php
if (
    empty($is_embedded_knit_card_generation) &&
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' &&
    isset($_GET['program_id']) &&
    intval($_GET['program_id']) > 0
) {
    header('Location: knit_card.php?generate_program_id=' . intval($_GET['program_id']));
    exit();
}

date_default_timezone_set('Asia/Dhaka');
$bdHour  = (int)date('G');
$bdMin   = (int)date('i');
$totMins = ($bdHour * 60) + $bdMin;

// 6:00 AM (360 mins) to 2:00 PM (840 mins) => Shift A
// 2:01 PM (841 mins) to 10:00 PM (1320 mins) => Shift B
// 10:01 PM (1321 mins) to 5:59 AM (359 mins) => Shift C
if ($totMins >= 360 && $totMins <= 840) {
    $shift = 'A';
} elseif ($totMins >= 841 && $totMins <= 1320) {
    $shift = 'B';
} else {
    $shift = 'C';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config.php';

if (!isset($_SESSION['username'])) {
    echo "<script>alert('You must be logged in'); window.location.href='login.php';</script>";
    exit();
}

$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : (isset($_POST['program_id']) ? intval($_POST['program_id']) : 0);

if ($program_id <= 0) {
    header("Location: knit_card.php?error=Invalid+program+ID");
    exit();
}

// 1. Read selected Knitting Program using KPTID (Rule 2)
$stmt = $db->prepare("SELECT * FROM knitting_program WHERE KPTID = ?");
if (!$stmt) {
    header("Location: knit_card.php?error=" . urlencode("Database error: " . $db->error));
    exit();
}
$stmt->bind_param("i", $program_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows == 0) {
    header("Location: knit_card.php?error=Knitting+Program+not+found");
    exit();
}

$prog = $res->fetch_assoc();
$stmt->close();

// 2. Fetch already carded quantity for this program from knit_card dynamically
$already_carded = 0.00;
$rolls_complete = 0;
$kc_prog_col = get_knit_card_program_col($db);
$p_kptid = intval($prog['KPTID']);
$p_prog_no = !empty($prog['PROGRAM_NO']) ? strval($prog['PROGRAM_NO']) : strval($p_kptid);
$sum_stmt = $db->prepare("SELECT SUM(QTY) AS total_carded, COUNT(*) AS rolls_complete
                          FROM knit_card
                          WHERE ({$kc_prog_col} = ? OR {$kc_prog_col} = ?)
                            AND LOWER(TRIM(COALESCE(COLOR, ''))) = LOWER(TRIM(?))");
if ($sum_stmt) {
    $program_color = trim((string)($prog['COLOR'] ?? ''));
    $sum_stmt->bind_param("sss", $p_prog_no, $p_kptid, $program_color);
    $sum_stmt->execute();
    $res_sum = $sum_stmt->get_result();
    if ($res_sum && $row_sum = $res_sum->fetch_assoc()) {
        $already_carded = floatval($row_sum['total_carded'] ?? 0);
        $rolls_complete = intval($row_sum['rolls_complete'] ?? 0);
    }
    $sum_stmt->close();
}

$program_qty = floatval($prog['QTY'] ?? 0);
$remaining_qty = max(0.00, $program_qty - $already_carded);

// 3. Obtain corresponding SUB_TID and BOOKING from program to load information from knitting_input
$sub_tid = $prog['SUB_TID'] ?? '';
$booking = $prog['PO_NUMBER'] ?? '';
$input = null;

if (!empty($sub_tid) || !empty($booking)) {
    $stmt_in = $db->prepare("SELECT * FROM knitting_input WHERE KITID = ? OR PO_NUMBER = ? LIMIT 1");
    if ($stmt_in) {
        $stmt_in->bind_param("ss", $sub_tid, $booking);
        $stmt_in->execute();
        $res_in = $stmt_in->get_result();
        if ($res_in && $res_in->num_rows > 0) {
            $input = $res_in->fetch_assoc();
        }
        $stmt_in->close();
    }
}

// Auto-populate required information combining knitting_program and knitting_input
$card_date          = date('Y-m-d');
$p_kptid            = intval($prog['KPTID']);
$p_sub_tid          = $sub_tid;
$p_buyer            = !empty($prog['BUYER']) ? $prog['BUYER'] : ($input['BUYER'] ?? '');
$p_customer         = !empty($prog['CUSTOMER']) ? $prog['CUSTOMER'] : ($input['CUSTOMER'] ?? '');
$p_booking          = !empty($prog['PO_NUMBER']) ? $prog['PO_NUMBER'] : ($input['BOOKING'] ?? '');
$p_sono             = !empty($prog['SONO']) ? $prog['SONO'] : ($input['SONO'] ?? '');
$p_style            = !empty($prog['STYLE']) ? $prog['STYLE'] : ($input['STYLE'] ?? '');
$p_mcno             = !empty($prog['MCNO']) ? $prog['MCNO'] : ($input['MCNO'] ?? '');
$p_mcdia            = !empty($prog['MCDIA']) ? $prog['MCDIA'] : ($input['MCDIA'] ?? ($input['MC_DIA'] ?? ''));
$p_finish_dia       = !empty($prog['FDIA']) ? $prog['FDIA'] : ($input['FINISH_DIA'] ?? '');
$p_finish_gsm       = !empty($prog['FGSM']) ? $prog['FGSM'] : ($input['FINISH_GSM'] ?? '');
$p_open_tube        = !empty($prog['O_T']) ? $prog['O_T'] : ($input['OPEN_TUBE'] ?? 'O');
$p_fabrics          = !empty($prog['FTYPE']) ? $prog['FTYPE'] : ($input['FABRICS_TYPE'] ?? '');
$p_yarn_type        = !empty($prog['YTYPE']) ? $prog['YTYPE'] : ($input['YARN_TYPE'] ?? '');
$p_yarn_count       = !empty($prog['YCOUNT']) ? $prog['YCOUNT'] : ($input['YCOUNT'] ?? '');
$p_color            = !empty($prog['COLOR']) ? $prog['COLOR'] : ($input['COLOR'] ?? '');
$p_lot_no           = !empty($prog['LOT']) ? $prog['LOT'] : ($input['LOT'] ?? '');
$p_yarn_brand       = !empty($prog['YBRAND']) ? $prog['YBRAND'] : ($input['YBRAND'] ?? '');
$p_remarks          = !empty($prog['REMARKS']) ? $prog['REMARKS'] : ($input['REMARKS'] ?? '');
$p_knit_m_desc      = !empty($prog['KNIT_M_DESCRIPTION']) ? $prog['KNIT_M_DESCRIPTION'] : ($input['KNIT_M_DESCRIPTION'] ?? '');
$p_knit_mat_code    = !empty($prog['KNIT_MATERIAL_CODE']) ? $prog['KNIT_MATERIAL_CODE'] : ($input['KNIT_MATERIAL_CODE'] ?? '');
$p_category         = $prog['CATEGORY'] ?? ($prog['Category'] ?? '');
$p_sl_vdq           = (isset($prog['SL']) && trim((string)$prog['SL']) !== '') ? trim((string)$prog['SL']) : (isset($input['SL']) && trim((string)$input['SL']) !== '' ? trim((string)$input['SL']) : '');

$default_qty        = $remaining_qty;
$authorised_by      = '';
$error              = '';

$operator_id = trim((string)($_SESSION['user_id'] ?? ''));
if ($operator_id === '') {
    $operator_id = trim((string)($_SESSION['operator_id'] ?? $_SESSION['knitting_qc_id'] ?? $_SESSION['username'] ?? ''));
}
$operator_name = trim((string)($_SESSION['user_name'] ?? $_SESSION['operator_name'] ?? ''));
if ($operator_name === '') {
    $operator_name = trim((string)($_SESSION['username'] ?? $operator_id));
}

// Make sure knit_card has the UID column that stores the operator ID
$uid_col_chk = @$db->query("SHOW COLUMNS FROM knit_card LIKE 'UID'");
if ($uid_col_chk && $uid_col_chk->num_rows === 0) {
    @$db->query("ALTER TABLE knit_card ADD COLUMN UID varchar(100) DEFAULT NULL AFTER UNAME");
}

if ($remaining_qty <= 0) {
    $error = "No remaining quantity is available for this program.";
}

// 4. Save Logic (Rule 6 & Rule 7 with Concurrency & Transaction Safety)
// Fetch MCNO list for dropdown
$mcno_list = [];
$mcno_res = $db->query("SELECT MCNO FROM mcno ORDER BY MCNO ASC");
if ($mcno_res) {
    while ($mcno_row = $mcno_res->fetch_assoc()) {
        $mcno_list[] = $mcno_row['MCNO'];
    }
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_knit_card'])) {
    $raw_machines = isset($_POST['machine_no']) ? (array)$_POST['machine_no'] : (isset($_POST['MCNO']) ? (array)$_POST['MCNO'] : []);
    $raw_shifts   = isset($_POST['shift']) ? (array)$_POST['shift'] : (isset($_POST['SHIFT']) ? (array)$_POST['SHIFT'] : []);
    $raw_qtys     = isset($_POST['required_qty']) ? (array)$_POST['required_qty'] : (isset($_POST['REQ_QTY']) ? (array)$_POST['REQ_QTY'] : []);

    $rows_data = [];
    $total_user_req_qty = 0.00;
    $row_count = max(count($raw_machines), count($raw_qtys));

    for ($i = 0; $i < $row_count; $i++) {
        $mc  = isset($raw_machines[$i]) ? trim($raw_machines[$i]) : '';
        $sh  = $shift; // Always strictly auto real-time shift
        $qty = isset($raw_qtys[$i]) ? floatval($raw_qtys[$i]) : 0.0;

        if (empty($mc)) {
            $error = "Row #" . ($i + 1) . ": Machine Number (M/C No) is required.";
            break;
        }
        if ($qty <= 0) {
            $error = "Row #" . ($i + 1) . ": Required Quantity must be a positive number greater than 0.";
            break;
        }

        $rows_data[] = [
            'mcno'  => $mc,
            'shift' => $sh,
            'qty'   => $qty
        ];
        $total_user_req_qty += $qty;
    }

    if (empty($error) && $operator_id === '') {
        $error = "Unable to determine the logged-in user. Please log in again.";
    }

    if (empty($error)) {
        if (empty($rows_data)) {
            $error = "Please add at least one production row.";
        } elseif ($total_user_req_qty <= 0) {
            $error = "Total required quantity must be greater than 0.";
        } else {
            $transaction_started = false;
            try {
                if (!$db->begin_transaction()) {
                    throw new Exception("Unable to start database transaction: " . $db->error);
                }
                $transaction_started = true;

                // Lock the knitting_program row to prevent concurrent modifications on this program
                $stmt_lock = $db->prepare("SELECT QTY FROM knitting_program WHERE KPTID = ? FOR UPDATE");
                if (!$stmt_lock) {
                    throw new Exception("Database lock error: " . $db->error);
                }
                $stmt_lock->bind_param("i", $p_kptid);
                $stmt_lock->execute();
                $res_lock = $stmt_lock->get_result();
                if (!$res_lock || $res_lock->num_rows === 0) {
                    $stmt_lock->close();
                    throw new Exception("Knitting program not found.");
                }
                $lock_row = $res_lock->fetch_assoc();
                $stmt_lock->close();

                $program_qty_locked = floatval($lock_row['QTY'] ?? 0);

                // Fetch current sum of QTY for this program from knit_card (using lock since it's in transaction)
                $stmt_sum = $db->prepare("SELECT SUM(QTY) AS total_carded
                                          FROM knit_card
                                          WHERE ({$kc_prog_col} = ? OR {$kc_prog_col} = ?)
                                            AND LOWER(TRIM(COALESCE(COLOR, ''))) = LOWER(TRIM(?))");
                if (!$stmt_sum) {
                    throw new Exception("Database query error: " . $db->error);
                }
                $stmt_sum->bind_param("sss", $p_prog_no, $p_kptid, $p_color);
                $stmt_sum->execute();
                $res_sum = $stmt_sum->get_result();
                $already_carded_locked = 0.00;
                if ($res_sum && $row_sum = $res_sum->fetch_assoc()) {
                    $already_carded_locked = floatval($row_sum['total_carded'] ?? 0);
                }
                $stmt_sum->close();

                $remaining_qty_locked = max(0.00, $program_qty_locked - $already_carded_locked);

                // Check if remaining quantity is zero
                if ($remaining_qty_locked <= 0) {
                    throw new Exception("No remaining quantity is available for this program.");
                }

                // Check if user required quantity exceeds remaining quantity
                if ($total_user_req_qty > ($remaining_qty_locked + 0.0001)) {
                    throw new Exception("Total required quantity (" . number_format($total_user_req_qty, 2) . " KG) exceeds the remaining program quantity (" . number_format($remaining_qty_locked, 2) . " KG).");
                }

                // Generate next KNITCARD number (always increments globally, even for the same PO)
                $next_knitcard = 200000001;
                $res_mc = $db->query("SELECT MAX(KNITCARD) AS mx FROM knit_card");
                if ($res_mc && ($r_mc = $res_mc->fetch_assoc()) && !empty($r_mc['mx'])) {
                    $next_knitcard = intval($r_mc['mx']) + 1;
                }
                $res_mc->free();

                // Insert into knit_card dynamically (including UID and UNAME)
                $ins = $db->prepare("
                    INSERT INTO knit_card (
                        {$kc_prog_col}, KNITCARD, MCNO, QTY, PO_NUMBER, SONO, BUYER, STYLE, COLOR,
                        FGSM, FDIA, O_T, FTYPE, YTYPE, CUSTOMER, YCOUNT, SL, MCDIA,
                        FEEDER_PLAN, LOT, SHIFT, KNIT_MATERIAL_CODE, KNIT_M_DESCRIPTION, UNAME,
                        UID, YBRAND, CATEGORY
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$ins) {
                    throw new Exception("Failed to prepare insert query: " . $db->error);
                }

                $p_fplan   = !empty($prog['FEEDER_PLAN']) ? $prog['FEEDER_PLAN'] : ($input['FEEDER_PLAN'] ?? '');
                $p_uname   = $operator_name !== '' ? $operator_name : $operator_id;
                $p_uid     = $operator_id;

                $inserted_ids = [];
                $inserted_knitcard_numbers = [];
                $current_knitcard = $next_knitcard;

                foreach ($rows_data as $row_item) {
                    $r_mcno  = $row_item['mcno'];
                    $r_shift = $row_item['shift'];
                    $r_qty   = round($row_item['qty']);

                    $ins->bind_param(
                        "sisdsssssssssssssssssssssss",
                        $p_prog_no,
                        $current_knitcard,
                        $r_mcno,
                        $r_qty,
                        $p_booking,
                        $p_sono,
                        $p_buyer,
                        $p_style,
                        $p_color,
                        $p_finish_gsm,
                        $p_finish_dia,
                        $p_open_tube,
                        $p_fabrics,
                        $p_yarn_type,
                        $p_customer,
                        $p_yarn_count,
                        $p_sl_vdq,
                        $p_mcdia,
                        $p_fplan,
                        $p_lot_no,
                        $r_shift,
                        $p_knit_mat_code,
                        $p_knit_m_desc,
                        $p_uname,
                        $p_uid,
                        $p_yarn_brand,
                        $p_category
                    );

                    if (!$ins->execute()) {
                        $ins_err = $ins->error;
                        throw new Exception("Failed to generate Knit Card: " . $ins_err);
                    }

                    $inserted_ids[] = $ins->insert_id;
                    $inserted_knitcard_numbers[] = (string)$current_knitcard;
                    $current_knitcard++;
                }

                $ins->close();

                if (!$db->commit()) {
                    throw new Exception("Unable to commit Knit Card generation: " . $db->error);
                }
                $transaction_started = false;

                $success_msg = count($inserted_knitcard_numbers) > 1
                    ? count($inserted_knitcard_numbers) . " Knit Cards saved successfully. Knit Card Nos: " . implode(', ', $inserted_knitcard_numbers)
                    : "Knit Card saved successfully. Knit Card No: " . $inserted_knitcard_numbers[0];

                $_SESSION['knit_card_generation_success'] = $success_msg;
                header("Location: knit_card.php", true, 303);
                exit();

            } catch (Exception $e) {
                if ($transaction_started) {
                    $db->rollback();
                }
                $error = $e->getMessage();
            }
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['save_knit_card'])) {
    $_SESSION['knit_card_generation_error'] = $error !== '' ? $error : 'Knit Card could not be generated.';
    header("Location: knit_card.php", true, 303);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Knit Card #<?php echo $p_kptid; ?> | Purbani Fabrics</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/mycss.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-canvas: #f8fafc;
            --surface-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --text-muted-light: #cbd5e1;
            
            --color-success: #16a34a;
            --color-success-light: #dcfce7;
            --color-success-border: #bbf7d0;
            
            --color-blue: #2563eb;
            --color-blue-light: #eff6ff;
            --color-blue-border: #dbeafe;
            
            --color-purple: #7c3aed;
            --color-purple-light: #f3e8ff;
            --color-purple-border: #e9d5ff;

            --color-amber: #d97706;
            --color-amber-light: #fef3c7;
            --color-amber-border: #fde68a;
            
            --border-color: #e2e8f0;
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
            
            --radius-input: 10px;
            --radius-box: 12px;
            --radius-card: 16px;
        }

        body {
            background-color: var(--bg-canvas);
            font-family: var(--font-main);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            padding: 16px;
        }

        .main-container {
            max-width: 1440px;
            margin: 0 auto;
        }

        /* ── PAGE HEADER (TOP BAR) ── */
        .top-bar {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            border-radius: 12px;
            padding: 14px 20px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .top-bar-icon {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .top-bar-title-wrap h1 {
            font-weight: 700;
            font-size: 18px;
            margin: 0;
            color: #ffffff;
            letter-spacing: -0.3px;
        }

        .top-bar-subtitle {
            font-size: 12px;
            color: #93c5fd;
            margin: 2px 0 0 0;
            font-weight: 400;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .pill-badge {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
        }

        .btn-back {
            background: #000000;
            color: #ffffff;
            font-weight: 600;
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 8px;
            border: 2px solid #000000;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back:hover {
            background: #ffffff;
            border-color: #000000;
            color: #000000;
            text-decoration: none;
        }

        /* ── WORKSPACE CARDS ── */
        .workspace-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
            padding: 10px 14px;
            margin-bottom: 10px;
        }

        .card-header-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 6px;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 6px;
        }

        .card-header-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .badge-pill-header {
            font-size: 9.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-status-blue {
            background: var(--color-blue-light);
            color: var(--color-blue);
            border: 1px solid var(--color-blue-border);
        }
        .badge-status-purple {
            background: var(--color-purple-light);
            color: var(--color-purple);
            border: 1px solid var(--color-purple-border);
        }

        /* ── TARGET PRODUCTION METRICS (4-COLUMN RESPONSIVE COMPACT BOX GRID) ── */
        .metrics-grid-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 4px;
        }

        @media (max-width: 992px) {
            .metrics-grid-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .metrics-grid-container {
                grid-template-columns: 1fr;
            }
        }

        .metric-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 10px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            transition: all 0.2s ease;
        }
        .metric-box:hover {
            border-color: #cbd5e1;
            background: #ffffff;
        }

        .metric-val-blue {
            font-size: 14px;
            font-weight: 800;
            color: var(--color-blue);
        }
        .metric-val-gray {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-secondary);
        }
        .metric-val-green {
            font-size: 14px;
            font-weight: 800;
            color: var(--color-success);
        }
        .metric-lbl {
            font-size: 9.5px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ── 4-6 ITEMS PER LINE RESPONSIVE SPECIFICATION BOX GRID (COMPACT) ── */
        .spec-grid-container {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
        }

        @media (max-width: 1400px) {
            .spec-grid-container {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 992px) {
            .spec-grid-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 640px) {
            .spec-grid-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 380px) {
            .spec-grid-container {
                grid-template-columns: 1fr;
            }
        }

        .spec-item-box {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 5px 8px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 2px;
            min-height: 44px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
            transition: all 0.2s ease;
        }

        .spec-item-box:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04);
        }

        .spec-box-head {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .spec-box-value {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-primary);
            word-break: break-word;
            line-height: 1.25;
        }

        /* ── FORM FIELDS & ALLOCATION CARDS (COMPACT) ── */
        .form-label-custom {
            font-size: 10px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
            display: block;
        }
        .required-label::after {
            content: " *";
            color: #ef4444;
        }
        
        .form-select-custom, .form-input-custom {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 3px 8px !important;
            font-weight: 600 !important;
            font-size: 11.5px !important;
            color: var(--text-primary) !important;
            transition: all 0.2s ease !important;
            width: 100%;
            height: 30px !important;
            line-height: 1.2 !important;
        }
        .form-select-custom:focus, .form-input-custom:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1) !important;
            outline: 0 !important;
        }

        /* Quantity Input Suffix */
        .quantity-input-wrapper {
            display: flex;
        }
        .quantity-input-wrapper .form-input-custom {
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
            border-right: none !important;
        }
        .input-group-addon-custom {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-left: none;
            color: #475569;
            font-weight: 700;
            font-size: 11px;
            padding: 0 8px;
            height: 30px;
            border-top-right-radius: 6px;
            border-bottom-right-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
        }

        .validation-msg {
            font-size: 11px;
            font-weight: 600;
            color: var(--color-success);
        }
        .allocation-validation {
            min-height: 40px;
            margin-top: 12px;
            padding: 10px 12px;
            border: 1px solid #d6e5fa;
            border-radius: 10px;
            background: #f3f8ff;
            line-height: 1.4;
        }

        /* ── DYNAMIC ALLOCATION ROWS (COMPACT) ── */
        .allocation-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 10px;
            margin-bottom: 8px;
            position: relative;
            transition: all 0.2s ease;
        }
        .allocation-card:hover {
            border-color: #cbd5e1;
            background: #ffffff;
        }
        .allocation-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .allocation-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .allocation-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        @media (max-width: 768px) {
            .allocation-grid {
                grid-template-columns: 1fr;
            }
        }

        .btn-remove-row {
            background: #ffffff;
            border: 1px solid #fecaca;
            color: #ef4444;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-remove-row:hover {
            background: #fef2f2;
            border-color: #f87171;
        }

        .btn-add-row {
            background: #ffffff;
            border: 1px dashed var(--color-blue);
            color: var(--color-blue);
            font-size: 11.5px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 8px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            cursor: pointer;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }
        .btn-add-row:hover {
            background: var(--color-blue-light);
            border-style: solid;
        }

        .allocation-summary-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 6px 10px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 11px;
        }

        /* ── BOTTOM ACTION BAR ── */
        .bottom-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .btn-cancel {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #334155 !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 10px 20px !important;
            border-radius: 8px !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
        }
        .btn-cancel:hover {
            background: #f8fafc !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }

        .btn-submit {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 10px 24px !important;
            border-radius: 8px !important;
            border: none !important;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.15) !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease !important;
        }
        .btn-submit:hover:not([disabled]) {
            filter: brightness(1.15);
        }
        .btn-submit[disabled] {
            background: #cbd5e1 !important;
            color: #94a3b8 !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            opacity: 0.7;
        }

        body {
            min-width: 320px;
            padding: clamp(12px, 2.4vw, 30px);
            background: #f3f6fb;
            color: #172033;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }
        .main-container { width: 100%; max-width: none; }
        .top-bar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            justify-content: start;
            align-items: center;
            gap: clamp(12px, 1.8vw, 22px);
            padding: clamp(14px, 1.8vw, 22px);
            margin-bottom: 16px;
            color: #172033;
            background: #ffffff;
            border: 1px solid #e1e7f0;
            border-radius: 15px;
            box-shadow: 0 6px 18px rgba(27, 45, 78, 0.06);
        }
        .top-bar-title-wrap { min-width: 0; justify-self: center; text-align: center; }
        .btn-back { justify-self: start; }
        .top-bar-title-wrap h1 {
            margin: 0;
            color: #172033;
            font-size: clamp(20px, 2.5vw, 27px);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.6px;
            overflow-wrap: anywhere;
        }
        .top-bar-title-wrap h1 span { color: #2563eb; }
        .top-bar-subtitle {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }
        .btn-back {
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid #000000;
            border-radius: 10px;
            background: #000000;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }
        .btn-back::before { content: "\2190"; margin-right: 8px; font-size: 18px; line-height: 1; }
        .btn-back:hover {
            background: #ffffff;
            border-color: #000000;
            color: #000000;
            transform: translateY(-1px);
        }
        .header-operator {
            display: flex;
            align-items: center;
            justify-self: end;
            min-width: 190px;
            padding: 10px 14px;
            border: 1px solid #dfe7f2;
            border-radius: 11px;
            background: #f7f9fc;
            text-align: left;
        }
        .header-operator-item { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .header-operator-label {
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .header-operator-value {
            color: #1e3a8a;
            font-size: 13px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }
        .workspace-card {
            padding: clamp(14px, 1.6vw, 20px);
            margin-bottom: 14px;
            border: 1px solid #e0e7f1;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 5px 18px rgba(27, 45, 78, 0.045);
        }
        .card-header-custom {
            padding-bottom: 10px;
            margin-bottom: 12px;
            border-bottom: 1px solid #e9eef5;
        }
        .card-header-title {
            color: #1e293b;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0;
        }
        .badge-pill-header {
            padding: 5px 10px;
            font-size: 10px;
            letter-spacing: 0.25px;
        }
        .metrics-grid-container { gap: 12px; }
        .metric-box {
            min-width: 0;
            min-height: 74px;
            justify-content: center;
            gap: 7px;
            padding: 11px 14px;
            border-color: #e5eaf2;
            border-radius: 12px;
            background: #f8faff;
        }
        .metric-lbl { font-size: 11px; letter-spacing: 0.45px; }
        .metric-val-blue, .metric-val-gray, .metric-val-green { font-size: 20px; line-height: 1.1; }
        .spec-grid-container {
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
        }
        .spec-item-box {
            min-width: 0;
            min-height: 64px;
            justify-content: flex-start;
            gap: 5px;
            padding: 9px 10px;
            border-color: #e5eaf2;
            border-radius: 11px;
            background: #fbfcfe;
            box-shadow: none;
        }
        .spec-box-head {
            color: #64748b;
            font-size: 9px;
            letter-spacing: 0.45px;
        }
        .spec-box-value {
            color: #1e293b;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }
        .form-label-custom {
            margin-bottom: 6px;
            color: #53647d;
            font-size: 11px;
            letter-spacing: 0.4px;
        }
        .form-select-custom, .form-input-custom {
            min-height: 40px !important;
            height: auto !important;
            padding: 8px 11px !important;
            border-radius: 9px !important;
            font-size: 14px !important;
        }
        .allocation-card {
            padding: 16px;
            margin-bottom: 12px;
            border-color: #e1e8f2;
            border-radius: 12px;
            background: #f9fbfe;
        }
        .allocation-header {
            margin-bottom: 14px;
            padding-bottom: 10px;
        }
        .allocation-title { color: #34445d; font-size: 13px; }
        .allocation-grid { gap: 14px; }
        .btn-remove-row {
            min-height: 34px;
            padding: 5px 11px;
            border-radius: 8px;
            font-size: 12px;
            border-color: #dc2626;
            background: #dc2626;
            color: #ffffff;
        }
        .btn-remove-row:hover {
            background: #b91c1c;
            border-color: #b91c1c;
            color: #ffffff;
        }
        .btn-add-row {
            min-height: 44px;
            margin-bottom: 14px;
            border-radius: 10px;
            font-size: 13px;
        }
        .allocation-summary-box {
            gap: 10px;
            padding: 14px 16px;
            border-color: #d6e5fa;
            border-radius: 12px;
            background: #f3f8ff;
            font-size: 13px;
        }
        .validation-msg {
            padding-top: 8px;
            border-top: 1px solid #dce8f8;
            font-size: 12px;
            line-height: 1.5;
        }
        .bottom-actions {
            position: sticky;
            bottom: 12px;
            z-index: 5;
            justify-content: flex-end;
            padding: 12px;
            margin-top: 18px;
            border: 1px solid #e0e7f1;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 8px 24px rgba(27, 45, 78, 0.1);
            backdrop-filter: blur(8px);
        }
        .btn-cancel, .btn-submit {
            min-height: 46px;
            padding: 0 20px !important;
            border-radius: 10px !important;
            font-size: 14px !important;
        }
        .btn-submit { min-width: 220px; }
        .alert { border-radius: 12px !important; }
        @media (max-width: 1100px) {
            .spec-grid-container { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        }
        @media (max-width: 800px) {
            .top-bar { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .top-bar-title-wrap { grid-column: 1 / -1; grid-row: 2; }
            .btn-back { grid-column: 1; grid-row: 1; }
            .header-operator {
                grid-column: 2;
                grid-row: 1;
                justify-content: flex-start;
            }
            .spec-grid-container { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .top-bar {
                grid-template-columns: 1fr;
                justify-items: start;
                gap: 11px;
                padding: 14px;
                border-radius: 14px;
            }
            .top-bar-title-wrap { grid-column: auto; grid-row: auto; justify-self: center; width: 100%; }
            .btn-back { grid-column: auto; grid-row: auto; }
            .top-bar-title-wrap h1 { font-size: 21px; }
            .header-operator {
                grid-column: auto;
                grid-row: auto;
                width: 100%;
                justify-content: flex-start;
                justify-self: stretch;
            }
            .metrics-grid-container { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; }
            .metric-box { min-height: 68px; padding: 9px 10px; }
            .metric-lbl { font-size: 9px; }
            .metric-val-blue, .metric-val-gray, .metric-val-green { font-size: 18px; }
            .spec-grid-container { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 7px; }
            .spec-item-box { min-height: 58px; padding: 8px; }
            .workspace-card { padding: 12px; border-radius: 13px; }
            .card-header-custom { align-items: flex-start; }
            .allocation-grid { grid-template-columns: 1fr; gap: 12px; }
            .allocation-summary-box { padding: 12px; }
            .bottom-actions { bottom: 8px; gap: 8px; padding: 9px; }
            .btn-cancel { flex: 0 0 auto; }
            .btn-submit { min-width: 0; flex: 1 1 auto; }
        }
        @media (max-width: 380px) {
            .metrics-grid-container, .spec-grid-container { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .spec-box-value { font-size: 11px; }
            .bottom-actions { position: static; flex-direction: column-reverse; }
            .btn-cancel, .btn-submit { width: 100%; }
        }
    </style>
</head>

<body>

    <div class="main-container">

        <!-- ═══ PAGE HEADER (TOP BAR) ═══ -->
        <div class="top-bar">
            <a href="knit_card.php" class="btn-back">Back</a>
            <div class="top-bar-title-wrap">
                <h1>Generate Knit Card <span>(<?php echo htmlspecialchars(!empty($prog['PROGRAM_NO']) ? $prog['PROGRAM_NO'] : $p_kptid); ?>)</span></h1>
            </div>
            <div class="header-operator">
                <div class="header-operator-item">
                    <span class="header-operator-label">Operator</span>
                    <span class="header-operator-value"><?php echo htmlspecialchars(($operator_name ?: 'Unavailable') . ($operator_id !== '' ? ' (' . $operator_id . ')' : '')); ?></span>
                </div>
            </div>
        </div>

        <!-- ═══ ERROR DISPLAY ═══ -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 p-3 border-0 shadow-sm" style="background:#fef2f2; color:#991b1b;">
                <div class="d-flex align-items-center gap-2">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ═══ FORM & WORKSPACE LAYOUT ═══ -->
        <form method="POST" action="knit_card.php?generate_program_id=<?php echo $p_kptid; ?>">
            <input type="hidden" name="save_knit_card" value="1">
            <input type="hidden" name="program_id" value="<?php echo $p_kptid; ?>">

            <!-- 1. TARGET PRODUCTION SETTINGS (SUMMARY METRICS) -->
            <div class="workspace-card">

                <!-- 4 Boxed Metrics Row -->
                <div class="metrics-grid-container">
                    <div class="metric-box">
                        <span class="metric-lbl">Program Qty (KG)</span>
                        <span class="metric-val-blue"><?php echo number_format($program_qty, 2); ?></span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-lbl">Already Carded (KG)</span>
                        <span class="metric-val-gray"><?php echo number_format($already_carded, 2); ?></span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-lbl">Remaining (KG)</span>
                        <span class="metric-val-green" id="metricRemainingQty"><?php echo number_format($remaining_qty, 2); ?></span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-lbl">Rolls Complete</span>
                        <span class="metric-val-blue" id="rollsComplete"><?php echo number_format($rolls_complete); ?></span>
                    </div>
                </div>
            </div>

            <!-- 2. PROGRAM DETAILS & SPECIFICATIONS (4-6 ITEMS PER LINE BOX GRID) -->
            <div class="workspace-card">
                <div class="card-header-custom">
                    <h4 class="card-header-title">
                        Program Details & Specifications
                    </h4>
                    <span class="badge-pill-header badge-status-purple">Auto-Populated</span>
                </div>

                <!-- Program details are ordered for quick production-floor review. -->
                <div class="spec-grid-container">
                    <div class="spec-item-box">
                        <div class="spec-box-head">PO Number</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_booking ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Color</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_color ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">QTY (KG)</div>
                        <div class="spec-box-value">
                            <?php echo number_format($program_qty, 2); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">SONO</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_sono ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Buyer</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_buyer ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Style</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_style ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">MC Dia</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_mcdia ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Customer</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_customer ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">O / T</div>
                        <div class="spec-box-value">
                            <?php echo ($p_open_tube === 'T') ? 'Tube (T)' : 'Open (O)'; ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">SL / VQ</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_sl_vdq !== '' ? $p_sl_vdq : 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Fabrics Type</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_fabrics ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Finish GSM</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_finish_gsm ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Finish Dia</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_finish_dia ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Yarn Type</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_yarn_type ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Yarn Count</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_yarn_count ?: 'N/A'); ?>
                        </div>
                    </div>
                    <div class="spec-item-box">
                        <div class="spec-box-head">Yarn Brand</div>
                        <div class="spec-box-value">
                            <?php echo htmlspecialchars($p_yarn_brand ?: 'N/A'); ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- 3. SUB-TID ALLOCATIONS & MACHINE ASSIGNMENTS -->
            <div class="workspace-card mb-3">
                <div class="card-header-custom">
                    <h4 class="card-header-title">
                        Sub-TID Allocations & Machine Assignments
                    </h4>
                    <span class="badge-pill-header badge-status-blue">Editable</span>
                </div>

                <!-- Container for rows -->
                <div id="allocationRowsContainer">
                    <!-- Initial Row -->
                    <div class="allocation-card" data-row-idx="1">
                        <div class="allocation-header">
                            <span class="allocation-title">
                                <span class="row-title-text">Sub-TID #1</span>
                            </span>
                            <button type="button" class="btn-remove-row" style="display: none;" title="Remove this row">
                                Remove
                            </button>
                        </div>
                        <div class="allocation-grid">
                            <div>
                                <label class="form-label-custom required-label">Machine (M/C NO)</label>
                                <select name="machine_no[]" class="form-select-custom row-mcno" required>
                                    <option value="">-- Select M/C --</option>
                                    <?php foreach ($mcno_list as $mc): ?>
                                        <option value="<?php echo htmlspecialchars($mc); ?>" <?php echo ($p_mcno === $mc) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mc); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label-custom">Shift</label>
                                <input type="hidden" name="shift[]" value="<?php echo $shift; ?>">
                                <div class="form-input-custom bg-light fw-bold text-primary d-flex align-items-center justify-content-between">
                                    <span>Shift <?php echo $shift; ?></span>
                                    <span class="badge bg-primary text-white" style="font-size: 10px; font-weight:700;">Auto Real-time</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label-custom required-label">Card Quantity (KG)</label>
                                <div class="quantity-input-wrapper">
                                    <input type="number" step="0.01" min="0.01" max="<?php echo htmlspecialchars($remaining_qty); ?>" name="required_qty[]" class="form-input-custom row-qty" value="" required placeholder="Enter QTY">
                                    <span class="input-group-addon-custom">KG</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Row Button -->
                <button type="button" id="btnAddRow" class="btn-add-row">
                    Add Another Sub-TID Row
                </button>
                <div id="liveValidationMessage" class="validation-msg allocation-validation">
                </div>
            </div>

            <!-- Bottom Action Bar -->
            <div class="bottom-actions">
                <a href="knit_card.php" class="btn btn-cancel">
                    Cancel
                </a>
                <button type="submit" class="btn btn-submit" disabled>
                    Save & Generate Knit Card
                </button>
            </div>
        </form>
    </div>

    <!-- ═══ METRICS & DYNAMIC MULTI-ROW SCRIPT ═══ -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const initialRemaining = <?php echo floatval($remaining_qty); ?>;
            const defaultShift     = "<?php echo htmlspecialchars($shift); ?>";
            const operatorAvailable = <?php echo $operator_id !== '' ? 'true' : 'false'; ?>;

            const container             = document.getElementById("allocationRowsContainer");
            const btnAddRow             = document.getElementById("btnAddRow");
            const liveValidationMessage = document.getElementById("liveValidationMessage");
            const submitBtn             = document.querySelector(".btn-submit");

            // Pre-built machine options HTML for newly added rows
            const mcOptionsHtml = `
                <option value="">-- Select M/C --</option>
                <?php foreach ($mcno_list as $mc): ?>
                    <option value="<?php echo htmlspecialchars($mc); ?>"><?php echo htmlspecialchars($mc); ?></option>
                <?php endforeach; ?>
            `;

            function updateCalculations() {
                const rows = container.querySelectorAll(".allocation-card");
                let totalAllocated = 0;
                let hasIncompleteRows = rows.length === 0;

                rows.forEach(row => {
                    const qtyInput = row.querySelector(".row-qty");
                    const machineSelect = row.querySelector(".row-mcno");
                    const parsedQty = parseFloat(qtyInput.value);
                    const val = Number.isFinite(parsedQty) ? parsedQty : 0;
                    if (!machineSelect.value || !qtyInput.value || !Number.isFinite(val) || val <= 0 || !qtyInput.checkValidity()) {
                        hasIncompleteRows = true;
                    }
                    totalAllocated += val;
                });

                const netRemaining  = Math.max(0, initialRemaining - totalAllocated);
                const isExceeded    = totalAllocated > (initialRemaining + 0.0001);
                const isEmptyOrZero = totalAllocated <= 0;

                // Validation messaging & Submit button enabling
                if (!operatorAvailable) {
                    liveValidationMessage.className = "validation-msg allocation-validation text-danger";
                    liveValidationMessage.textContent = "Return to Knit Card and scan a valid operator before generating a card.";
                    if (submitBtn) submitBtn.disabled = true;
                } else if (initialRemaining <= 0) {
                    liveValidationMessage.className = "validation-msg allocation-validation text-danger";
                    liveValidationMessage.textContent = "Program quantity is already 100% carded.";
                    if (submitBtn) submitBtn.disabled = true;
                } else if (isExceeded) {
                    const exceedDiff = (totalAllocated - initialRemaining).toFixed(2);
                    liveValidationMessage.className = "validation-msg allocation-validation text-danger";
                    liveValidationMessage.textContent = "Exceeds available program capacity by " + exceedDiff + " KG";
                    if (submitBtn) submitBtn.disabled = true;
                } else if (hasIncompleteRows) {
                    liveValidationMessage.className = "validation-msg allocation-validation text-primary";
                    if (submitBtn) submitBtn.disabled = true;
                } else if (isEmptyOrZero) {
                    liveValidationMessage.className = "validation-msg allocation-validation text-primary";
                    liveValidationMessage.textContent = "Enter any amount up to " + initialRemaining.toFixed(2) + " KG to generate this card.";
                    if (submitBtn) submitBtn.disabled = true;
                } else {
                    liveValidationMessage.className = "validation-msg allocation-validation text-success";
                    if (submitBtn) submitBtn.disabled = false;
                }

                // Toggle visibility of Remove button on rows
                rows.forEach(row => {
                    const removeBtn = row.querySelector(".btn-remove-row");
                    if (removeBtn) {
                        removeBtn.style.display = rows.length > 1 ? "inline-flex" : "none";
                    }
                });
            }

            function renumberRows() {
                const rows = container.querySelectorAll(".allocation-card");
                rows.forEach((row, idx) => {
                    const rowNumber = idx + 1;
                    row.setAttribute("data-row-idx", rowNumber);
                    const title = row.querySelector(".row-title-text");
                    if (title) {
                        title.textContent = "Sub-TID #" + rowNumber;
                    }
                });
            }

            // Add Row Click Event
            if (btnAddRow) {
                btnAddRow.addEventListener("click", function() {
                    const newCard = document.createElement("div");
                    newCard.className = "allocation-card";
                    newCard.innerHTML = `
                        <div class="allocation-header">
                            <span class="allocation-title">
                                <span class="row-title-text">Sub-TID</span>
                            </span>
                            <button type="button" class="btn-remove-row" title="Remove this row">
                                Remove
                            </button>
                        </div>
                        <div class="allocation-grid">
                            <div>
                                <label class="form-label-custom required-label">Machine (M/C NO)</label>
                                <select name="machine_no[]" class="form-select-custom row-mcno" required>
                                    ${mcOptionsHtml}
                                </select>
                            </div>
                            <div>
                                <label class="form-label-custom">Shift</label>
                                <input type="hidden" name="shift[]" value="${defaultShift}">
                                <div class="form-input-custom bg-light fw-bold text-primary d-flex align-items-center justify-content-between">
                                    <span>Shift ${defaultShift}</span>
                                    <span class="badge bg-primary text-white" style="font-size: 10px; font-weight:700;">Auto Real-time</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label-custom required-label">Card Quantity (KG)</label>
                                <div class="quantity-input-wrapper">
                                    <input type="number" step="0.01" min="0.01" max="${initialRemaining}" name="required_qty[]" class="form-input-custom row-qty" value="" required placeholder="Enter quantity">
                                    <span class="input-group-addon-custom">KG</span>
                                </div>
                            </div>
                        </div>
                    `;

                    container.appendChild(newCard);
                    renumberRows();
                    updateCalculations();

                    const newSelect = newCard.querySelector(".row-mcno");
                    if (newSelect) newSelect.focus();
                });
            }

            // Remove Row Event Delegation
            container.addEventListener("click", function(e) {
                const removeBtn = e.target.closest(".btn-remove-row");
                if (removeBtn) {
                    const card = removeBtn.closest(".allocation-card");
                    if (card) {
                        card.remove();
                        renumberRows();
                        updateCalculations();
                    }
                }
            });

            // Input change delegation
            container.addEventListener("input", updateCalculations);
            container.addEventListener("change", updateCalculations);

            // Initial calculation run
            setTimeout(function() {
                updateCalculations();
            }, 100);
        });
    </script>

</body>
</html>
