<?php
// ajaxDyeing_batch_card_Insert.php
session_start();
require_once 'config.php';
require_once 'dyeing_process_fields.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

if (!isset($_SESSION['dyeing_batch']) || !is_array($_SESSION['dyeing_batch'])) {
    $_SESSION['dyeing_batch'] = [];
}
if (!isset($_SESSION['dyeing_batch']['rolls']) || !is_array($_SESSION['dyeing_batch']['rolls'])) {
    $_SESSION['dyeing_batch']['rolls'] = [];
}

try {
    ensureDyeingBatchQuantityColumns($db);
    ensureDyeingProcessColumns($db, 'dyeing_batch_card');
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    exit;
}

$rolls = array_values($_SESSION['dyeing_batch']['rolls']);
$dyeingMcno = isset($_SESSION['dyeing_batch']['dymcno'])
    ? trim((string)$_SESSION['dyeing_batch']['dymcno'])
    : '';
$selectedProcesses = isset($_SESSION['dyeing_batch']['processes']) &&
    is_array($_SESSION['dyeing_batch']['processes'])
    ? $_SESSION['dyeing_batch']['processes']
    : [];
$processFields = dyeingBatchCardProcessFieldNames();

if (count($rolls) === 0 || $dyeingMcno === '') {
    echo json_encode(['success' => false, 'message' => 'Select at least one roll and a Dyeing MC No to create a batch card']);
    exit;
}
if (count($selectedProcesses) === 0) {
    echo json_encode(['success' => false, 'message' => 'Select at least one Dyeing Process to create a batch card']);
    exit;
}
$batchColumnsResult = $db->query("SHOW COLUMNS FROM dyeing_batch_card");
if (!$batchColumnsResult) {
    echo json_encode(['success' => false, 'message' => 'Could not inspect batch card columns: ' . $db->error]);
    exit;
}
$batchColumns = [];
while ($column = $batchColumnsResult->fetch_assoc()) {
    $batchColumns[] = strtoupper($column['Field']);
}
if (in_array('MCNO', $batchColumns, true) && in_array('DYMCNO', $batchColumns, true)) {
    echo json_encode(['success' => false, 'message' => 'Both MCNO and DYMCNO columns exist in dyeing_batch_card. Resolve the schema before inserting.']);
    exit;
}
if (in_array('MCNO', $batchColumns, true)) {
    if (!$db->query("ALTER TABLE dyeing_batch_card CHANGE COLUMN MCNO DYMCNO VARCHAR(100) DEFAULT NULL")) {
        echo json_encode(['success' => false, 'message' => 'Could not rename MCNO to DYMCNO: ' . $db->error]);
        exit;
    }
} elseif (!in_array('DYMCNO', $batchColumns, true)) {
    if (!$db->query("ALTER TABLE dyeing_batch_card ADD COLUMN DYMCNO VARCHAR(100) DEFAULT NULL AFTER COLOR")) {
        echo json_encode(['success' => false, 'message' => 'Could not add DYMCNO column: ' . $db->error]);
        exit;
    }
}

foreach ([
    'YBRAND' => "VARCHAR(100) DEFAULT NULL",
    'CATEGORY' => "VARCHAR(100) DEFAULT NULL"
] as $column => $definition) {
    $columnResult = $db->query("SHOW COLUMNS FROM dyeing_batch_card LIKE '$column'");
    if (!$columnResult) {
        echo json_encode(['success' => false, 'message' => 'Could not check batch card column ' . $column . ': ' . $db->error]);
        exit;
    }
    if ($columnResult->num_rows === 0 && !$db->query("ALTER TABLE dyeing_batch_card ADD COLUMN `$column` $definition")) {
        echo json_encode(['success' => false, 'message' => 'Could not add batch card column ' . $column . ': ' . $db->error]);
        exit;
    }
}

// Assign next card number from DB
$r = mysqli_query($db, "SELECT COALESCE(MAX(CAST(BCARD AS UNSIGNED)), 4000000000) + 1 AS nxt FROM dyeing_batch_card");
if (!$r) {
    echo json_encode(['success' => false, 'message' => 'Could not generate the next batch card number: ' . $db->error]);
    exit;
}
$rowX = $r ? mysqli_fetch_assoc($r) : null;
if (!$rowX || !isset($rowX['nxt'])) {
    echo json_encode(['success' => false, 'message' => 'Could not generate the next batch card number']);
    exit;
}
$seq = (int)$rowX['nxt'];
$cardNo = (string)$seq;

$uname = $_SESSION['username'];
$budat = date('Y-m-d');

$inserted = 0;
$errorMsg = '';
$db->autocommit(false);

try {
    $lockStmt = $db->prepare(
        'SELECT QTY FROM knitting_store WHERE TRIM(ROLL) = ? ORDER BY KSTID DESC LIMIT 1 FOR UPDATE'
    );
    $usedStmt = $db->prepare(
        'SELECT COALESCE(SUM(CAST(RQTY AS DECIMAL(12,3))), 0) AS USED_QTY
         FROM dyeing_batch_card WHERE TRIM(ROLL) = ?'
    );
    if (!$lockStmt || !$usedStmt) {
        throw new RuntimeException('Could not validate current roll availability: ' . $db->error);
    }

    $remainingByRoll = [];
    foreach ($rolls as &$row) {
        $rollNo = isset($row['ROLL']) ? trim((string)$row['ROLL']) : '';
        $requiredQty = isset($row['RQTY']) && is_numeric($row['RQTY'])
            ? round((float)$row['RQTY'], 3)
            : 0;
        if ($rollNo === '' || !is_finite($requiredQty) || $requiredQty <= 0) {
            throw new RuntimeException('Invalid roll number or Required QTY in the batch selection.');
        }

        $lockStmt->bind_param('s', $rollNo);
        if (!$lockStmt->execute()) {
            throw new RuntimeException('Could not lock roll ' . $rollNo . ': ' . $lockStmt->error);
        }
        $storeResult = $lockStmt->get_result();
        $storeRow = $storeResult ? $storeResult->fetch_assoc() : null;
        if (!$storeRow || !is_numeric($storeRow['QTY'])) {
            throw new RuntimeException('Roll ' . $rollNo . ' is no longer available in Knitting Store.');
        }

        $usedStmt->bind_param('s', $rollNo);
        if (!$usedStmt->execute()) {
            throw new RuntimeException('Could not check previous usage for roll ' . $rollNo . ': ' . $usedStmt->error);
        }
        $usedResult = $usedStmt->get_result();
        $usedRow = $usedResult ? $usedResult->fetch_assoc() : null;
        $availableQty = (float)$storeRow['QTY'] - ($usedRow && is_numeric($usedRow['USED_QTY']) ? (float)$usedRow['USED_QTY'] : 0);
        if ($requiredQty - $availableQty > 0.000001) {
            throw new RuntimeException(
                'Required QTY for roll ' . $rollNo . ' exceeds its current Available QTY of ' .
                number_format(max(0, $availableQty), 3) . ' KG. Search again and retry.'
            );
        }

        $row['KQTY'] = $storeRow['QTY'];
        $row['AQTY'] = max(0, $availableQty - $requiredQty);
        $remainingByRoll[$rollNo] = $row['AQTY'];
    }
    unset($row);
    $lockStmt->close();
    $usedStmt->close();
} catch (Throwable $error) {
    if (isset($lockStmt) && $lockStmt) $lockStmt->close();
    if (isset($usedStmt) && $usedStmt) $usedStmt->close();
    $db->rollback();
    $db->autocommit(true);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    exit;
}

foreach ($rolls as $row) {
    $stmt = $db->prepare(
        "INSERT INTO dyeing_batch_card
         (BUDAT, BCARD, ROLL, PO_NUMBER, RACK, KQTY, AQTY, RQTY, SONO, SHIFT, BUYER, STYLE, COLOR,
          DYMCNO, MCDIA, CUSTOMER, O_T, SL, FTYPE, FGSM, FDIA, FEEDER_PLAN, LOT_NO,
          YTYPE, YCOUNT, YBRAND, CATEGORY, TPOINT, UNAME, SILICON, NONSILICON, NWASH,
          EWASH, ENZYME, NONENZYME, PEACH, BRUSH, AOP, SOFTENER, NONSOFTENER, OTHERS,
          SHEARING, SINGEING, HEAT_SET)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                 ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        $errorMsg = 'Database prepare error: ' . $db->error;
        break;
    }

    $vPO_NUMBER = isset($row['PO_NUMBER']) ? $row['PO_NUMBER'] : null;
    $vRACK = isset($row['RACK']) ? $row['RACK'] : null;
    $vKQTY = isset($row['KQTY']) ? $row['KQTY'] : null;
    $vAQTY = isset($row['AQTY']) ? $row['AQTY'] : null;
    $vRQTY = isset($row['RQTY']) ? $row['RQTY'] : null;
    $vSONO = isset($row['SONO']) ? $row['SONO'] : null;
    $vSHIFT = isset($row['SHIFT']) ? $row['SHIFT'] : null;
    $vBUYER = isset($row['BUYER']) ? $row['BUYER'] : null;
    $vSTYLE = isset($row['STYLE']) ? $row['STYLE'] : null;
    $vCOLOR = isset($row['COLOR']) ? $row['COLOR'] : null;
    $vMCDIA = isset($row['MCDIA']) ? $row['MCDIA'] : null;
    $vCUSTOMER = isset($row['CUSTOMER']) ? $row['CUSTOMER'] : null;
    $vYTYPE = isset($row['YTYPE']) ? $row['YTYPE'] : null;
    $vYCOUNT = isset($row['YCOUNT']) ? $row['YCOUNT'] : null;
    $vO_T = isset($row['O_T']) ? $row['O_T'] : null;
    $vSL = isset($row['SL']) ? $row['SL'] : null;
    $vFTYPE = isset($row['FTYPE']) ? $row['FTYPE'] : null;
    $vFGSM = isset($row['FGSM']) ? $row['FGSM'] : null;
    $vFDIA = isset($row['FDIA']) ? $row['FDIA'] : null;
    $vFEEDER_PLAN = isset($row['FEEDER_PLAN']) ? $row['FEEDER_PLAN'] : null;
    $vLOT_NO = isset($row['LOT_NO']) ? $row['LOT_NO'] : null;
    $vYBRAND = isset($row['YBRAND']) ? $row['YBRAND'] : null;
    $vCATEGORY = isset($row['CATEGORY']) ? $row['CATEGORY'] : null;
    $vTPOINT = isset($row['TPOINT']) ? $row['TPOINT'] : null;
    $vROLL = isset($row['ROLL']) ? $row['ROLL'] : '';
    $processValues = [];
    foreach ($processFields as $processField) {
        $processValues[$processField] = isset($selectedProcesses[$processField])
            ? $selectedProcesses[$processField]
            : '';
    }

    $stmt->bind_param(
        str_repeat('s', 44),
        $budat,
        $cardNo,
        $vROLL,
        $vPO_NUMBER,
        $vRACK,
        $vKQTY,
        $vAQTY,
        $vRQTY,
        $vSONO,
        $vSHIFT,
        $vBUYER,
        $vSTYLE,
        $vCOLOR,
        $dyeingMcno,
        $vMCDIA,
        $vCUSTOMER,
        $vO_T,
        $vSL,
        $vFTYPE,
        $vFGSM,
        $vFDIA,
        $vFEEDER_PLAN,
        $vLOT_NO,
        $vYTYPE,
        $vYCOUNT,
        $vYBRAND,
        $vCATEGORY,
        $vTPOINT,
        $uname,
        $processValues['SILICON'],
        $processValues['NONSILICON'],
        $processValues['NWASH'],
        $processValues['EWASH'],
        $processValues['ENZYME'],
        $processValues['NONENZYME'],
        $processValues['PEACH'],
        $processValues['BRUSH'],
        $processValues['AOP'],
        $processValues['SOFTENER'],
        $processValues['NONSOFTENER'],
        $processValues['OTHERS'],
        $processValues['SHEARING'],
        $processValues['SINGEING'],
        $processValues['HEAT_SET']
    );

    if (!$stmt->execute()) {
        $errorMsg = 'Insert error for roll ' . $row['ROLL'] . ': ' . $stmt->error;
        $stmt->close();
        break;
    }
    $stmt->close();
    $inserted++;
}

if ($inserted === count($rolls)) {
    $availabilityStmt = $db->prepare(
        'UPDATE dyeing_batch_card SET AQTY = ? WHERE TRIM(ROLL) = ?'
    );
    if (!$availabilityStmt) {
        $errorMsg = 'Could not prepare available quantity update: ' . $db->error;
        $inserted = 0;
    } else {
        foreach ($remainingByRoll as $rollNo => $remainingQty) {
            $availabilityStmt->bind_param('ds', $remainingQty, $rollNo);
            if (!$availabilityStmt->execute()) {
                $errorMsg = 'Could not update available quantity for roll ' . $rollNo . ': ' . $availabilityStmt->error;
                $inserted = 0;
                break;
            }
        }
        $availabilityStmt->close();
    }
}

if ($inserted === count($rolls)) {
    if (!$db->commit()) {
        $errorMsg = 'Database commit failed: ' . $db->error;
        $db->rollback();
        $db->autocommit(true);
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    unset($_SESSION['dyeing_batch']['rolls']);
    unset($_SESSION['dyeing_batch']['card_no']);
    unset($_SESSION['dyeing_batch']['created_at']);
    unset($_SESSION['dyeing_batch']['target_qty']);
    unset($_SESSION['dyeing_batch']['dymcno']);
    unset($_SESSION['dyeing_batch']['processes']);
    $_SESSION['dyeing_batch']['seq'] = $seq + 1;

    echo json_encode([
        'success' => true,
        'card_no' => $cardNo,
        'next_card_no' => $seq + 1,
        'count' => $inserted,
        'message' => 'Batch Card created with ' . $inserted . ' roll(s).'
    ]);
} else {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => $errorMsg !== '' ? $errorMsg : 'Database error during batch card insert']);
}

$db->autocommit(true);
exit;
?>
