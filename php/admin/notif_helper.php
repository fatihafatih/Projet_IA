<?php
function envoyerNotification(PDO $pdo, int $userId, string $type, string $titre, string $message): void {
    $pdo->prepare("
        INSERT INTO notifications (ID_USERS, type, titre, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ")->execute([$userId, $type, $titre, $message]);
}

function notifierValidation(PDO $pdo, int $creatorId, int $outilId, string $outilNom): void {
    envoyerNotification($pdo, $creatorId, 'alerte',
        '✅ Outil validé et publié !',
        "Bonne nouvelle ! Votre outil « $outilNom » a été validé par l'équipe SearchIA et est maintenant visible."
    );
}

function notifierRefus(PDO $pdo, int $creatorId, int $outilId, string $outilNom, string $cause = ''): void {
    $causeText = $cause !== ''
        ? "\n\n📋 Motif : $cause"
        : "\n\nLes informations fournies ne correspondent pas aux critères de qualité.";
    envoyerNotification($pdo, $creatorId, 'alerte',
        '⛔ Outil non validé',
        "Votre outil « $outilNom » n'a pas été approuvé.$causeText\n\nVous pouvez le modifier et le resoumettre."
    );
}

function notifierAdminNouvelOutil(PDO $pdo, int $adminId, string $nomOutil, string $nomUser): void {
    envoyerNotification($pdo, $adminId, 'alerte',
        "🆕 Nouvel outil soumis : $nomOutil",
        "$nomUser a soumis l'outil « $nomOutil ». En attente de vérification et validation."
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