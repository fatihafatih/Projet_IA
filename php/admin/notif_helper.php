<?php
function envoyerNotification(PDO $pdo, int $userId, string $type, string $titre, string $message): void {
    // Fix collation utf8mb3 -> utf8mb4 (emojis)
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo->prepare("
        INSERT INTO notifications (ID_USERS, type, titre, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ")->execute([$userId, $type, $titre, $message]);
}

function notifierValidation(PDO $pdo, int $creatorId, int $outilId, string $outilNom): void {
    envoyerNotification($pdo, $creatorId, 'alerte',
        'Outil valide et publie !',
        "Bonne nouvelle ! Votre outil « $outilNom » a ete valide par l'equipe SearchIA et est maintenant visible."
    );
}

function notifierRefus(PDO $pdo, int $creatorId, int $outilId, string $outilNom, string $cause = ''): void {
    $causeText = $cause !== ''
        ? "\n\nMotif : $cause"
        : "\n\nLes informations fournies ne correspondent pas aux criteres de qualite.";
    envoyerNotification($pdo, $creatorId, 'alerte',
        'Outil non valide',
        "Votre outil « $outilNom » n'a pas ete approuve.$causeText\n\nVous pouvez le modifier et le resoumettre."
    );
}

function notifierAdminNouvelOutil(PDO $pdo, int $adminId, string $nomOutil, string $nomUser): void {
    envoyerNotification($pdo, $adminId, 'alerte',
        "Nouvel outil soumis : $nomOutil",
        "$nomUser a soumis l'outil « $nomOutil ». En attente de verification et validation."
    );
}

function notifierTousLesAdmins(PDO $pdo, string $nomOutil, string $nomUser): void {
    $admins = $pdo->query(
        "SELECT id FROM users WHERE role IN ('admin', 'superadmin')"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        notifierAdminNouvelOutil($pdo, (int)$adminId, $nomOutil, $nomUser);
    }
}