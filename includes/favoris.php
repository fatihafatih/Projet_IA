<?php
session_start();
require_once 'connexionbd.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'non_connecte']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Récupérer les collections de l'utilisateur ────────────────────────────
if ($action === 'get_collections') {
    $id_outil = (int) ($_GET['id_outil'] ?? 0);

    // Crée "Mes favoris" si elle n'existe pas encore
    $check = $pdo->prepare("SELECT ID_COLLECTIONS FROM collections WHERE ID_USERS = ? AND name = 'Mes favoris'");
    $check->execute([$user_id]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO collections (name, ID_USERS) VALUES ('Mes favoris', ?)")->execute([$user_id]);
    }

    // Récupère toutes les collections avec le statut favori pour cet outil
    $stmt = $pdo->prepare("
        SELECT c.ID_COLLECTIONS, c.name,
               (SELECT COUNT(*) FROM favoris f 
                WHERE f.ID_COLLECTIONS = c.ID_COLLECTIONS 
                AND f.ID_OUTILS_IA = ? 
                AND f.ID_USERS = ?) AS is_fav
        FROM collections c
        WHERE c.ID_USERS = ?
        ORDER BY c.name = 'Mes favoris' DESC, c.name ASC
    ");
    $stmt->execute([$id_outil, $user_id, $user_id]);
    echo json_encode(['collections' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── Créer une nouvelle collection ─────────────────────────────────────────
if ($action === 'create_collection') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        echo json_encode(['error' => 'nom_vide']);
        exit;
    }

    // Vérifie que le nom n'existe pas déjà
    $check = $pdo->prepare("SELECT ID_COLLECTIONS FROM collections WHERE ID_USERS = ? AND name = ?");
    $check->execute([$user_id, $name]);
    if ($check->fetch()) {
        echo json_encode(['error' => 'existe_deja']);
        exit;
    }

    $pdo->prepare("INSERT INTO collections (name, ID_USERS) VALUES (?, ?)")->execute([$name, $user_id]);
    $new_id = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $new_id, 'name' => $name]);
    exit;
}

// ── Ajouter / Retirer un favori ───────────────────────────────────────────
if ($action === 'toggle_favori') {
    $id_outil = (int) ($_POST['id_outil'] ?? 0);
    $id_collection = (int) ($_POST['id_collection'] ?? 0);

    if (!$id_outil || !$id_collection) {
        echo json_encode(['error' => 'params_manquants']);
        exit;
    }

    // Vérifie que la collection appartient bien à l'utilisateur
    $check = $pdo->prepare("SELECT ID_COLLECTIONS FROM collections WHERE ID_COLLECTIONS = ? AND ID_USERS = ?");
    $check->execute([$id_collection, $user_id]);
    if (!$check->fetch()) {
        echo json_encode(['error' => 'collection_invalide']);
        exit;
    }

    // Toggle
    $exists = $pdo->prepare("SELECT ID_FAVORIS FROM favoris WHERE ID_USERS = ? AND ID_OUTILS_IA = ? AND ID_COLLECTIONS = ?");
    $exists->execute([$user_id, $id_outil, $id_collection]);

    if ($exists->fetch()) {
        $pdo->prepare("DELETE FROM favoris WHERE ID_USERS = ? AND ID_OUTILS_IA = ? AND ID_COLLECTIONS = ?")
            ->execute([$user_id, $id_outil, $id_collection]);
        echo json_encode(['success' => true, 'state' => 'removed']);
    } else {
        $pdo->prepare("INSERT INTO favoris (ID_USERS, ID_OUTILS_IA, ID_COLLECTIONS) VALUES (?, ?, ?)")
            ->execute([$user_id, $id_outil, $id_collection]);
        echo json_encode(['success' => true, 'state' => 'added']);
    }
    exit;
}

echo json_encode(['error' => 'action_inconnue']);