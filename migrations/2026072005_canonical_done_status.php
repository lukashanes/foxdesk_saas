<?php

/**
 * Collapse every terminal ticket status into the single canonical Done status.
 * Archiving remains represented by tickets.is_archived and is not a status.
 */
return static function (PDO $db): void {
    $tableExists = static function (string $table) use ($db): bool {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    };

    $db->beginTransaction();
    try {
        $doneStmt = $db->query(
            "SELECT * FROM statuses
             WHERE LOWER(slug) = 'done' OR LOWER(name) = 'done'
             ORDER BY CASE WHEN LOWER(slug) = 'done' THEN 0 ELSE 1 END, id
             LIMIT 1"
        );
        $done = $doneStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$done) {
            $nextOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM statuses')->fetchColumn();
            $insert = $db->prepare(
                "INSERT INTO statuses (name, slug, color, sort_order, is_default, is_closed)
                 VALUES ('Done', 'done', '#34c759', ?, 0, 1)"
            );
            $insert->execute([$nextOrder]);
            $doneId = (int) $db->lastInsertId();
        } else {
            $doneId = (int) $done['id'];
            $update = $db->prepare(
                "UPDATE statuses
                 SET name = 'Done', slug = 'done', color = '#34c759', is_closed = 1
                 WHERE id = ?"
            );
            $update->execute([$doneId]);
        }

        $terminalStmt = $db->prepare(
            "SELECT id FROM statuses
             WHERE id <> ? AND (
                 is_closed = 1
                 OR LOWER(slug) IN ('done', 'completed', 'complete', 'closed', 'cancelled', 'canceled', 'dokonceno', 'hotovo')
                 OR LOWER(name) IN ('done', 'completed', 'complete', 'closed', 'cancelled', 'canceled', 'dokončeno', 'dokonceno', 'hotovo')
             )
             ORDER BY id"
        );
        $terminalStmt->execute([$doneId]);
        $duplicateIds = array_map('intval', $terminalStmt->fetchAll(PDO::FETCH_COLUMN));

        foreach ($duplicateIds as $duplicateId) {
            foreach (['tickets', 'recurring_tasks'] as $table) {
                if ($tableExists($table)) {
                    $stmt = $db->prepare("UPDATE `{$table}` SET status_id = ? WHERE status_id = ?");
                    $stmt->execute([$doneId, $duplicateId]);
                }
            }

            if ($tableExists('ticket_history')) {
                foreach (['old_value', 'new_value'] as $column) {
                    $stmt = $db->prepare(
                        "UPDATE ticket_history SET `{$column}` = ?
                         WHERE field_name = 'status_id' AND `{$column}` = ?"
                    );
                    $stmt->execute([(string) $doneId, (string) $duplicateId]);
                }
            }

            $delete = $db->prepare('DELETE FROM statuses WHERE id = ?');
            $delete->execute([$duplicateId]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
};
