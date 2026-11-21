<?php

function dyeingProcessFieldNames()
{
    return [
        'SILICON', 'NONSILICON', 'NWASH', 'EWASH', 'ENZYME', 'NONENZYME',
        'PEACH', 'BRUSH', 'AOP', 'SOFTENER', 'NONSOFTENER', 'OTHERS'
    ];
}

function dyeingBatchCardExtraProcessFieldNames()
{
    return ['SHEARING', 'SINGEING', 'HEAT_SET'];
}

function dyeingBatchCardProcessFieldNames()
{
    return array_merge(dyeingProcessFieldNames(), dyeingBatchCardExtraProcessFieldNames());
}

function ensureDyeingBatchQuantityColumns($db)
{
    $result = $db->query('SHOW COLUMNS FROM `dyeing_batch_card`');
    if (!$result) {
        throw new RuntimeException('Could not inspect dyeing_batch_card quantity columns: ' . $db->error);
    }

    $columns = [];
    while ($column = $result->fetch_assoc()) {
        $columns[strtoupper($column['Field'])] = strtolower($column['Type']);
    }
    $result->free();

    if (isset($columns['QTY']) && !isset($columns['KQTY'])) {
        if (!$db->query('ALTER TABLE `dyeing_batch_card` CHANGE COLUMN `QTY` `KQTY` DOUBLE DEFAULT NULL')) {
            throw new RuntimeException('Could not rename dyeing_batch_card QTY to KQTY: ' . $db->error);
        }
        unset($columns['QTY']);
        $columns['KQTY'] = 'double';
    } elseif (isset($columns['QTY']) && isset($columns['KQTY'])) {
        if (!isset($columns['RQTY'])) {
            if (!$db->query('ALTER TABLE `dyeing_batch_card` ADD COLUMN `RQTY` DOUBLE DEFAULT NULL')) {
                throw new RuntimeException('Could not add dyeing_batch_card RQTY: ' . $db->error);
            }
            $columns['RQTY'] = 'double';
        }
        if (!$db->query('UPDATE `dyeing_batch_card` SET `KQTY` = COALESCE(`KQTY`, `QTY`), `RQTY` = COALESCE(`RQTY`, `QTY`)')) {
            throw new RuntimeException('Could not migrate existing dyeing batch quantities: ' . $db->error);
        }
        if (!$db->query('ALTER TABLE `dyeing_batch_card` DROP COLUMN `QTY`')) {
            throw new RuntimeException('Could not remove legacy dyeing_batch_card QTY column: ' . $db->error);
        }
        unset($columns['QTY']);
    }

    if (!isset($columns['KQTY'])) {
        throw new RuntimeException('Neither QTY nor KQTY exists in dyeing_batch_card.');
    }
    foreach (['AQTY', 'RQTY'] as $quantityColumn) {
        if (isset($columns[$quantityColumn])) {
            continue;
        }
        if (!$db->query(
            'ALTER TABLE `dyeing_batch_card` ADD COLUMN `' . $quantityColumn . '` DOUBLE DEFAULT NULL'
        )) {
            throw new RuntimeException('Could not add dyeing_batch_card ' . $quantityColumn . ': ' . $db->error);
        }
        $columns[$quantityColumn] = 'double';
    }

    foreach (['KQTY', 'AQTY', 'RQTY'] as $quantityColumn) {
        if (!isset($columns[$quantityColumn]) || strpos($columns[$quantityColumn], 'double') === 0) {
            continue;
        }
        if (!$db->query(
            'ALTER TABLE `dyeing_batch_card` MODIFY COLUMN `' . $quantityColumn . '` DOUBLE DEFAULT NULL'
        )) {
            throw new RuntimeException('Could not update dyeing_batch_card ' . $quantityColumn . ' storage: ' . $db->error);
        }
    }

    if (!$db->query(
        'UPDATE `dyeing_batch_card`
         SET `RQTY` = COALESCE(`RQTY`, `KQTY`),
             `AQTY` = COALESCE(`AQTY`, GREATEST(COALESCE(`KQTY`, 0) - COALESCE(`RQTY`, `KQTY`, 0), 0))
         WHERE `RQTY` IS NULL OR `AQTY` IS NULL'
    )) {
        throw new RuntimeException('Could not initialize dyeing batch available quantities: ' . $db->error);
    }

    $orderResult = $db->query('SHOW COLUMNS FROM `dyeing_batch_card`');
    if (!$orderResult) {
        throw new RuntimeException('Could not verify dyeing_batch_card quantity column order: ' . $db->error);
    }
    $order = [];
    while ($column = $orderResult->fetch_assoc()) {
        $order[] = strtoupper($column['Field']);
    }
    $orderResult->free();

    if (array_search('AQTY', $order, true) !== array_search('KQTY', $order, true) + 1 &&
        !$db->query('ALTER TABLE `dyeing_batch_card` MODIFY COLUMN `AQTY` DOUBLE DEFAULT NULL AFTER `KQTY`')) {
        throw new RuntimeException('Could not position dyeing_batch_card AQTY after KQTY: ' . $db->error);
    }

    $orderResult = $db->query('SHOW COLUMNS FROM `dyeing_batch_card`');
    if (!$orderResult) {
        throw new RuntimeException('Could not verify dyeing_batch_card quantity column order: ' . $db->error);
    }
    $order = [];
    while ($column = $orderResult->fetch_assoc()) {
        $order[] = strtoupper($column['Field']);
    }
    $orderResult->free();

    if (array_search('RQTY', $order, true) !== array_search('AQTY', $order, true) + 1 &&
        !$db->query('ALTER TABLE `dyeing_batch_card` MODIFY COLUMN `RQTY` DOUBLE DEFAULT NULL AFTER `AQTY`')) {
        throw new RuntimeException('Could not position dyeing_batch_card RQTY after AQTY: ' . $db->error);
    }
}

function ensureDyeingCountColumn($db, $table)
{
    $allowedTables = [
        'dyeing_batch_verification',
        'dyeing_roll_verification',
        'dyeing_load',
        'dyeing_unload'
    ];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Unsupported dyeing count table.');
    }

    $result = $db->query('SHOW COLUMNS FROM `' . $table . '` LIKE \'DCOUNT\'');
    if (!$result) {
        throw new RuntimeException('Could not inspect ' . $table . ' DCOUNT column: ' . $db->error);
    }
    $exists = $result->num_rows > 0;
    $result->free();
    if (!$exists && !$db->query(
        'ALTER TABLE `' . $table . '` ADD COLUMN `DCOUNT` INT NOT NULL DEFAULT 1'
    )) {
        throw new RuntimeException('Could not add DCOUNT to ' . $table . ': ' . $db->error);
    }
}

function ensureDyeingProcessColumns($db, $table)
{
    $allowedTables = [
        'dyeing_batch_card',
        'dyeing_batch_delivery',
        'dyeing_batch_verification',
        'dyeing_load',
        'dyeing_unload'
    ];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Unsupported dyeing process table.');
    }

    $columnsResult = $db->query('SHOW COLUMNS FROM `' . $table . '`');
    if (!$columnsResult) {
        throw new RuntimeException('Could not inspect ' . $table . ' columns: ' . $db->error);
    }
    $columns = [];
    while ($column = $columnsResult->fetch_assoc()) {
        $columns[] = strtoupper($column['Field']);
    }
    $columnsResult->free();

    $addedFields = [];
    $processFields = $table === 'dyeing_batch_card'
        ? dyeingBatchCardProcessFieldNames()
        : dyeingProcessFieldNames();
    foreach ($processFields as $field) {
        if (in_array($field, $columns, true)) {
            continue;
        }
        if (!$db->query(
            'ALTER TABLE `' . $table . '` ADD COLUMN `' . $field . '` VARCHAR(100) DEFAULT NULL'
        )) {
            throw new RuntimeException('Could not add ' . $field . ' to ' . $table . ': ' . $db->error);
        }
        $addedFields[] = $field;
    }

    $sources = [
        'dyeing_batch_delivery' => 'dyeing_batch_card',
        'dyeing_batch_verification' => 'dyeing_batch_delivery',
        'dyeing_load' => 'dyeing_batch_card',
        'dyeing_unload' => 'dyeing_load'
    ];
    if (!$addedFields || !isset($sources[$table])) {
        return;
    }

    $sourceFields = [];
    $assignments = [];
    $emptyChecks = [];
    foreach ($addedFields as $field) {
        $sourceFields[] = 'MAX(`' . $field . '`) AS `' . $field . '`';
        $assignments[] = '`destination`.`' . $field . '` = CASE
            WHEN `destination`.`' . $field . '` IS NULL OR `destination`.`' . $field . '` = \'\'
            THEN `source`.`' . $field . '`
            ELSE `destination`.`' . $field . '`
        END';
        $emptyChecks[] = '(`destination`.`' . $field . '` IS NULL OR `destination`.`' . $field . '` = \'\')';
    }
    $sourceTable = $sources[$table];
    $backfillSql = 'UPDATE `' . $table . '` AS `destination`
        INNER JOIN (
            SELECT `BCARD`, `ROLL`, ' . implode(', ', $sourceFields) . '
            FROM `' . $sourceTable . '`
            GROUP BY `BCARD`, `ROLL`
        ) AS `source`
            ON TRIM(`source`.`BCARD`) = TRIM(`destination`.`BCARD`)
            AND TRIM(`source`.`ROLL`) = TRIM(`destination`.`ROLL`)
        SET ' . implode(', ', $assignments) . '
        WHERE (' . implode(' OR ', $emptyChecks) . ')';
    if (!$db->query($backfillSql)) {
        throw new RuntimeException('Could not backfill dyeing process fields in ' . $table . ': ' . $db->error);
    }
}
