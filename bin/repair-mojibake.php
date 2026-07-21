<?php

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/database.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

$options = getopt('', ['apply', 'confirm:', 'show-values', 'table:', 'limit:']);
$apply = array_key_exists('apply', $options);
$showValues = array_key_exists('show-values', $options);
$tableFilter = trim((string) ($options['table'] ?? ''));
$limit = max(0, (int) ($options['limit'] ?? 0));

if ($apply && ($options['confirm'] ?? '') !== 'REPAIR-MOJIBAKE') {
    fwrite(STDERR, "Apply mode requires --confirm=REPAIR-MOJIBAKE. Run without --apply first.\n");
    exit(2);
}

function mojibake_score(string $value): int
{
    $patterns = [
        '/(?:Ã.|Â.|â€.|â€™|â€œ|â€|â€“|â€”|â€¦|ðŸ|ï¿½)/u',
        '/(?:Ă.|Ä.|Å.|Ĺ.|Ĺ™|Ĺľ|Ä›|ÄŤ|ÄŹ|Åˆ|Ĺ|Ĺ |Ĺ˝|ĹŻ)/u',
        '/\x{FFFD}/u',
    ];
    $score = 0;
    foreach ($patterns as $pattern) {
        $matches = preg_match_all($pattern, $value);
        $score += $matches === false ? 0 : $matches;
    }
    return $score;
}

function mojibake_candidate(string $value): ?string
{
    $best = $value;
    $bestScore = mojibake_score($value);
    if ($bestScore === 0) {
        return null;
    }

    foreach (['Windows-1250', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
        $candidate = @iconv('UTF-8', $encoding . '//IGNORE', $value);
        if (!is_string($candidate) || $candidate === '' || preg_match('//u', $candidate) !== 1) {
            continue;
        }
        $score = mojibake_score($candidate);
        if ($score < $bestScore) {
            $best = $candidate;
            $bestScore = $score;
        }
    }

    return $best !== $value ? $best : null;
}

function quoted_identifier(string $name): string
{
    validate_sql_identifier($name);
    return '`' . $name . '`';
}

$pdo = get_db();
$schema = (string) DB_NAME;
$tableSql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'";
$tableParams = [$schema];
if ($tableFilter !== '') {
    validate_sql_identifier($tableFilter);
    $tableSql .= ' AND TABLE_NAME = ?';
    $tableParams[] = $tableFilter;
}
$tableSql .= ' ORDER BY TABLE_NAME';
$tableStmt = $pdo->prepare($tableSql);
$tableStmt->execute($tableParams);
$tables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);

$findings = 0;
$repairable = 0;
$updated = 0;
$scannedRows = 0;
$skippedTables = [];
$sensitivePattern = '/(?:password|token|secret|signature|cipher|hash|api[_-]?key|webhook)/i';

foreach ($tables as $table) {
    $pkStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_KEY = 'PRI' ORDER BY ORDINAL_POSITION");
    $pkStmt->execute([$schema, $table]);
    $primaryKeys = $pkStmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($primaryKeys) !== 1) {
        $skippedTables[] = $table;
        continue;
    }
    $pk = (string) $primaryKeys[0];

    $columnStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json') ORDER BY ORDINAL_POSITION");
    $columnStmt->execute([$schema, $table]);
    $columns = array_values(array_filter($columnStmt->fetchAll(PDO::FETCH_COLUMN), static fn(string $column): bool => !preg_match($sensitivePattern, $column)));
    if ($columns === []) {
        continue;
    }

    $select = implode(', ', array_map('quoted_identifier', array_merge([$pk], $columns)));
    $sql = 'SELECT ' . $select . ' FROM ' . quoted_identifier($table) . ' ORDER BY ' . quoted_identifier($pk);
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    if ($apply) {
        $pdo->beginTransaction();
    }
    try {
        foreach ($pdo->query($sql) as $row) {
            $scannedRows++;
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                if (!is_string($value) || $value === '') {
                    continue;
                }
                $score = mojibake_score($value);
                if ($score === 0) {
                    continue;
                }
                $findings++;
                $candidate = mojibake_candidate($value);
                $repairable += $candidate === null ? 0 : 1;
                $record = [
                    'table' => $table,
                    'column' => $column,
                    'row' => (string) $row[$pk],
                    'score' => $score,
                    'repairable' => $candidate !== null,
                ];
                if ($showValues) {
                    $record['before'] = mb_substr($value, 0, 160);
                    $record['after'] = $candidate === null ? null : mb_substr($candidate, 0, 160);
                }
                echo json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

                if ($apply && $candidate !== null) {
                    $update = $pdo->prepare('UPDATE ' . quoted_identifier($table) . ' SET ' . quoted_identifier($column) . ' = ? WHERE ' . quoted_identifier($pk) . ' = ?');
                    $update->execute([$candidate, $row[$pk]]);
                    $updated += $update->rowCount();
                }
            }
        }
        if ($apply) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($apply && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'scanned_rows' => $scannedRows,
    'findings' => $findings,
    'repairable' => $repairable,
    'updated' => $updated,
    'skipped_tables_without_single_primary_key' => $skippedTables,
];
fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
