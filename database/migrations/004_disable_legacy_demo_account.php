<?php
/**
 * Migration 004 — disable the legacy demo account
 * ------------------------------------------------------------
 * database/legacy/migrate_multi_user.sql (now deleted) created
 * demo@recovery.local and documented its password in a comment. That file
 * stays readable in the public git history forever, so any database built
 * from it would have a login anyone could look up.
 *
 * We DISABLE rather than delete: when the app went multi-user, existing
 * workouts were assigned to that account, and deleting it would cascade
 * and erase them. "!" is not a valid password hash, so password_verify()
 * can never succeed against it — the account simply can't log in anymore.
 */

return function (PDO $db): void {
    $stmt = $db->prepare(
        "UPDATE users SET password_hash = '!disabled-legacy-demo-account'
         WHERE email = 'demo@recovery.local'"
    );
    $stmt->execute();

    echo $stmt->rowCount() > 0
        ? "\n    Legacy demo account found and disabled (its data is kept).\n    "
        : "\n    No legacy demo account in this database.\n    ";
};