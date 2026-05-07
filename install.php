<?php
/**
 * ACCIT — Script d'installation
 * Crée la table MySQL `contacts`.
 * ⚠️  Exécuter une seule fois via le navigateur, puis supprimer ce fichier.
 * URL : https://accit.fr/install.php
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contacts (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            prenom      VARCHAR(100) NOT NULL,
            nom         VARCHAR(100) NOT NULL,
            email       VARCHAR(255) NOT NULL,
            telephone   VARCHAR(50)  DEFAULT NULL,
            societe     VARCHAR(200) NOT NULL,
            sujet       VARCHAR(100) NOT NULL,
            message     TEXT         NOT NULL,
            ip          VARCHAR(45)  DEFAULT NULL,
            INDEX idx_email (email),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo '<p style="font-family:sans-serif;color:green;font-size:1.2rem;">
        ✅ Table <strong>contacts</strong> créée avec succès.<br>
        <strong>Supprimez ce fichier (install.php) immédiatement.</strong>
    </p>';

} catch (PDOException $e) {
    http_response_code(500);
    echo '<p style="font-family:sans-serif;color:red;">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
}
