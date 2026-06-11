<?php
// ── Connexion + session AVANT tout output ───────────────────────
require_once '../../includes/connexionbd.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$old = [];
// ── Charger les modèles ──────────────────────────────────────
$modeles = $pdo->query("
    SELECT m.ID_MODEL, m.name, p.name AS provider_name
    FROM models m
    LEFT JOIN providers p ON m.ID_PROVIDERS = p.ID_PROVIDERS
    WHERE m.status = 'actif'
    ORDER BY p.name, m.name
")->fetchAll(PDO::FETCH_ASSOC);

// ── Charger les catégories ──────────────────────────────────────
$categories = $pdo->query("SELECT ID_CATEGORIE, name FROM CATEGORIE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ── Traitement POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    // ── Champs principaux
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $version = trim($_POST['version'] ?? '');
    $id_cat = $_POST['id_categorie'] ?? '';
    $id_models = $_POST['id_models'] ?? []; // tableau car multi-select
    $id_models = array_filter(array_map('intval', $id_models)); // nettoyer
    if (strlen($nom) < 2)
        $errors['nom'] = 'Au moins 2 caractères.';
    if (strlen($nom) > 150)
        $errors['nom'] = 'Maximum 150 caractères.';
    if (strlen($description) < 10)
        $errors['description'] = 'Au moins 10 caractères.';
    if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL))
        $errors['url'] = 'URL invalide (http:// ou https://).';
    if (!empty($logo_url) && !filter_var($logo_url, FILTER_VALIDATE_URL))
        $errors['logo_url'] = 'URL du logo invalide.';
    if (!empty($version) && !is_numeric($version))
        $errors['version'] = 'Doit être un nombre (ex: 1.0).';
    if (empty($id_cat) || !is_numeric($id_cat))
        $errors['id_categorie'] = 'Veuillez choisir une catégorie.';

    if (empty($errors['nom'])) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM OUTILS_IA WHERE nom = ?");
        $check->execute([$nom]);
        if ($check->fetchColumn() > 0)
            $errors['nom'] = 'Un outil avec ce nom existe déjà.';
    }

    // ── Champs secondaires : avantages / inconvénients
    $avantages = array_filter(array_map('trim', $_POST['avantages'] ?? []), fn($v) => $v !== '');
    $inconvenients = array_filter(array_map('trim', $_POST['inconvenients'] ?? []), fn($v) => $v !== '');

    // ── Champs secondaires : performance
    $perf_fields = ['rapidite', 'qualite', 'qualite_image', 'credibilite', 'score_global'];
    $perf = [];
    $has_perf = false;
    foreach ($perf_fields as $f) {
        $val = trim($_POST['perf_' . $f] ?? '');
        if ($val !== '') {
            $has_perf = true;
            if (!is_numeric($val) || $val < 0 || $val > 10)
                $errors['perf_' . $f] = 'Entre 0 et 10.';
            $perf[$f] = $val;
        } else {
            $perf[$f] = null;
        }
    }

    // ── Champs secondaires : caractéristiques
    $car_names = array_map('trim', $_POST['car_name'] ?? []);
    $car_descs = array_map('trim', $_POST['car_desc'] ?? []);
    $car_stats = $_POST['car_statut'] ?? [];
    $cars = [];
    foreach ($car_names as $i => $cn) {
        if ($cn !== '') {
            if (strlen($cn) > 150)
                $errors['car_name_' . $i] = 'Maximum 150 caractères.';
            $cars[] = [
                'name' => $cn,
                'description' => $car_descs[$i] ?? '',
                'statut' => in_array($car_stats[$i] ?? '', ['actif', 'inactif']) ? $car_stats[$i] : 'actif',
            ];
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Insert outil
            $stmt = $pdo->prepare("
                INSERT INTO OUTILS_IA (nom, description, logo_url, url, version, status, ID_CATEGORIE, ID_USERS_CREATE)
                VALUES (?, ?, ?, ?, ?, 'en_attente', ?, ?)
            ");
            $stmt->execute([$nom, $description, $logo_url ?: null, $url ?: null, $version ?: null, $id_cat, $userId]);
            $outilId = $pdo->lastInsertId();

            // Liaison outil ↔ modèles
            if (!empty($id_models)) {
                $stmtTM = $pdo->prepare("INSERT INTO tool_models (ID_OUTILS_IA, ID_MODEL) VALUES (?, ?)");
                foreach ($id_models as $modelId) {
                    $stmtTM->execute([$outilId, $modelId]);
                }
            }

            // Avantages / inconvénients
            $stmtAI = $pdo->prepare("INSERT INTO avantages_inconvenients (type, description, ID_OUTILS_IA) VALUES (?,?,?)");
            foreach ($avantages as $a)
                $stmtAI->execute(['avantage', $a, $outilId]);
            foreach ($inconvenients as $i)
                $stmtAI->execute(['inconvenient', $i, $outilId]);

            // Performance
            if ($has_perf) {
                $stmtP = $pdo->prepare("
                    INSERT INTO performance (rapidite, qualite, qualite_image, credibilite, score_global, ID_OUTILS_IA, ID_USERS)
                    VALUES (?,?,?,?,?,?,?)
                ");
                $stmtP->execute([$perf['rapidite'], $perf['qualite'], $perf['qualite_image'], $perf['credibilite'], $perf['score_global'], $outilId, $userId]);
            }

            // Caractéristiques
            $stmtC = $pdo->prepare("INSERT INTO caracteristiques (name, description, statut) VALUES (?,?,?)");
            // Note: caracteristiques n'a pas ID_OUTILS_IA dans le schéma fourni, on insère sans liaison directe
            foreach ($cars as $car) {
                $stmtC->execute([$car['name'], $car['description'], $car['statut']]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "L'outil \"$nom\" a été soumis avec succès. Il sera visible après validation.";
            header('Location: outils.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['global'] = 'Erreur lors de l\'enregistrement. Veuillez réessayer.';
        }
    }
}

$pageTitle = 'Ajouter un outil';
require 'layout.php';
?>

<div class="ao-wrapper">

    <!-- Breadcrumb -->
    <div class="ao-breadcrumb">
        <a href="outils.php" class="ao-back">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <polyline points="15 18 9 12 15 6" />
            </svg>
            Mes Outils
        </a>
        <span class="ao-sep">/</span>
        <span class="ao-current">Ajouter un outil</span>
    </div>

    <!-- Titre -->
    <div class="ao-hero">
        <h1 class="ao-title">Soumettre un outil IA</h1>
    </div>

    <!-- Banner attente -->
    <div class="ao-notice">
        <span>Votre outil passera en statut <strong>Actif</strong> après approbation de l'équipe.</span>
    </div>

    <!-- Global error -->
    <?php if (isset($errors['global'])): ?>
        <div class="ao-alert-err"><?= $errors['global'] ?></div>
    <?php endif; ?>

    <!-- Steps nav -->
    <div class="ao-steps" id="stepsNav">
        <button type="button" class="ao-step active" data-step="1" onclick="goStep(1)">
            <span class="ao-step-num">1</span>
            <span class="ao-step-label">Informations principales</span>
        </button>
        <div class="ao-step-line"></div>
        <button type="button" class="ao-step" data-step="2" onclick="goStep(2)">
            <span class="ao-step-num">2</span>
            <span class="ao-step-label">Avantages &amp; Inconvénients</span>
        </button>
        <div class="ao-step-line"></div>
        <button type="button" class="ao-step" data-step="3" onclick="goStep(3)">
            <span class="ao-step-num">3</span>
            <span class="ao-step-label">Performance</span>
        </button>
        <div class="ao-step-line"></div>
        <button type="button" class="ao-step" data-step="4" onclick="goStep(4)">
            <span class="ao-step-num">4</span>
            <span class="ao-step-label">Caractéristiques</span>
        </button>
    </div>

    <form method="POST" id="addOutilForm" novalidate>

        <!-- ══════════════════════════════════════════ -->
        <!-- STEP 1 : Infos principales                -->
        <!-- ══════════════════════════════════════════ -->
        <div class="ao-card" id="step1">

            <div class="ao-card-header">
                <div class="ao-card-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                </div>
                <div>
                    <h2 class="ao-card-title">Informations principales</h2>
                    <p class="ao-card-sub">Champs obligatoires pour identifier l'outil</p>
                </div>
            </div>

            <!-- Nom + Catégorie -->
            <div class="ao-row-2">
                <div class="ao-field">
                    <label class="ao-label">Nom de l'outil <span class="ao-req">*</span></label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($old['nom'] ?? '') ?>"
                        placeholder="Ex : ChatGPT, Midjourney…"
                        class="ao-input <?= isset($errors['nom']) ? 'ao-input-err' : '' ?>" oninput="clearErr(this)"
                        autocomplete="off">
                    <?php if (isset($errors['nom'])): ?>
                        <div class="ao-err"><?= $errors['nom'] ?></div>
                    <?php endif; ?>
                </div>

                <div class="ao-field">
                    <label class="ao-label">Catégorie <span class="ao-req">*</span></label>
                    <div class="ao-select-wrap">
                        <select name="id_categorie"
                            class="ao-input ao-select <?= isset($errors['id_categorie']) ? 'ao-input-err' : '' ?>"
                            onchange="clearErr(this)">
                            <option value="">— Choisir —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['ID_CATEGORIE'] ?>" <?= (isset($old['id_categorie']) && $old['id_categorie'] == $cat['ID_CATEGORIE']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="ao-select-icon" width="14" height="14" fill="none" stroke="currentColor"
                            stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </div>
                    <?php if (isset($errors['id_categorie'])): ?>
                        <div class="ao-err"><?= $errors['id_categorie'] ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="ao-field">
                <label class="ao-label">
                    Description <span class="ao-req">*</span>
                    <span id="descCount" class="ao-charcount">0/500</span>
                </label>
                <textarea name="description" rows="5" maxlength="500"
                    placeholder="Décrivez ce que fait cet outil, ses points forts, son cas d'usage…"
                    class="ao-input ao-textarea <?= isset($errors['description']) ? 'ao-input-err' : '' ?>"
                    oninput="clearErr(this);updateCount(this,500,'descCount')"><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="ao-err"><?= $errors['description'] ?></div>
                <?php endif; ?>
            </div>

            <!-- URL + Logo -->
            <div class="ao-row-2">
                <div class="ao-field">
                    <label class="ao-label">URL de l'outil</label>
                    <div class="ao-input-icon-wrap">
                        <svg class="ao-input-icon" width="14" height="14" fill="none" stroke="currentColor"
                            stroke-width="2" viewBox="0 0 24 24">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                        </svg>
                        <input type="url" name="url" value="<?= htmlspecialchars($old['url'] ?? '') ?>"
                            placeholder="https://exemple.com"
                            class="ao-input ao-input-has-icon <?= isset($errors['url']) ? 'ao-input-err' : '' ?>"
                            oninput="clearErr(this)">
                    </div>
                    <?php if (isset($errors['url'])): ?>
                        <div class="ao-err"><?= $errors['url'] ?></div>
                    <?php endif; ?>
                </div>

                <div class="ao-field">
                    <label class="ao-label">URL du logo</label>
                    <div class="ao-input-icon-wrap">
                        <svg class="ao-input-icon" width="14" height="14" fill="none" stroke="currentColor"
                            stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <polyline points="21 15 16 10 5 21" />
                        </svg>
                        <input type="url" name="logo_url" value="<?= htmlspecialchars($old['logo_url'] ?? '') ?>"
                            placeholder="https://exemple.com/logo.png"
                            class="ao-input ao-input-has-icon <?= isset($errors['logo_url']) ? 'ao-input-err' : '' ?>"
                            oninput="clearErr(this);previewLogo(this.value)">
                    </div>
                    <?php if (isset($errors['logo_url'])): ?>
                        <div class="ao-err"><?= $errors['logo_url'] ?></div>
                    <?php endif; ?>
                    <div id="logoPreview" class="ao-logo-preview"
                        style="display:<?= !empty($old['logo_url']) ? 'flex' : 'none' ?>;">
                        <img id="logoImg" src="<?= htmlspecialchars($old['logo_url'] ?? '') ?>" alt="aperçu"
                            onerror="document.getElementById('logoPreview').style.display='none'">
                        <span>Aperçu du logo</span>
                    </div>
                </div>
            </div>

            <!-- Version -->
            <div class="ao-field ao-field-sm">
                <label class="ao-label">Version</label>
                <input type="text" name="version" value="<?= htmlspecialchars($old['version'] ?? '') ?>"
                    placeholder="Ex : 1.0, 2.5" class="ao-input <?= isset($errors['version']) ? 'ao-input-err' : '' ?>"
                    oninput="clearErr(this)">
                <?php if (isset($errors['version'])): ?>
                    <div class="ao-err"><?= $errors['version'] ?></div>
                <?php endif; ?>
            </div>

            <!-- Modèles -->
            <div class="ao-field">
                <label class="ao-label">Modèles IA utilisés</label>
                <div class="ao-models-wrap">
                    <?php foreach ($modeles as $mod): ?>
                        <label class="ao-model-item">
                            <input type="checkbox" name="id_models[]" value="<?= $mod['ID_MODEL'] ?>"
                                <?= in_array($mod['ID_MODEL'], array_map('intval', $old['id_models'] ?? [])) ? 'checked' : '' ?>>
                            <span class="ao-model-label">
                                <span class="ao-model-provider"><?= htmlspecialchars($mod['provider_name'] ?? '?') ?></span>
                                <?= htmlspecialchars($mod['name']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="ao-field-hint">Sélectionnez un ou plusieurs modèles sur lesquels repose cet outil.</p>
            </div>

            <div class="ao-step-actions">
                <a href="outils.php" class="ao-btn-cancel">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    Annuler
                </a>
                <button type="button" class="ao-btn-next" onclick="validateStep1()">
                    Suivant
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ -->
        <!-- STEP 2 : Avantages & Inconvénients        -->
        <!-- ══════════════════════════════════════════ -->
        <div class="ao-card ao-card-hidden" id="step2">

            <div class="ao-card-header">
                <div class="ao-card-icon ao-card-icon--green">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z" />
                        <path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3" />
                    </svg>
                </div>
                <div>
                    <h2 class="ao-card-title">Avantages &amp; Inconvénients</h2>
                    <p class="ao-card-sub">Optionnel — ajoutez autant de points que souhaité</p>
                </div>
            </div>

            <div class="ao-ai-grid">
                <!-- Avantages -->
                <div class="ao-ai-col ao-ai-col--pro">
                    <div class="ao-ai-col-header">
                        <svg width="16" height="16" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <span>Avantages</span>
                    </div>
                    <div id="avantagesList">
                        <?php
                        $avOld = $old['avantages'] ?? [''];
                        foreach ($avOld as $idx => $av): ?>
                            <div class="ao-ai-row">
                                <input type="text" name="avantages[]" value="<?= htmlspecialchars($av) ?>"
                                    placeholder="Ex : Gratuit, Rapide, API disponible…" class="ao-input">
                                <button type="button" class="ao-ai-remove" onclick="removeRow(this)" title="Supprimer">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"
                                        viewBox="0 0 24 24">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="ao-ai-add ao-ai-add--pro"
                        onclick="addRow('avantagesList','avantages[]','Ex : Gratuit, Rapide…')">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Ajouter un avantage
                    </button>
                </div>

                <!-- Inconvénients -->
                <div class="ao-ai-col ao-ai-col--con">
                    <div class="ao-ai-col-header">
                        <svg width="16" height="16" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        <span>Inconvénients</span>
                    </div>
                    <div id="inconvenientsList">
                        <?php
                        $inOld = $old['inconvenients'] ?? [''];
                        foreach ($inOld as $idx => $inc): ?>
                            <div class="ao-ai-row">
                                <input type="text" name="inconvenients[]" value="<?= htmlspecialchars($inc) ?>"
                                    placeholder="Ex : Payant, Lent, Peu précis…" class="ao-input">
                                <button type="button" class="ao-ai-remove" onclick="removeRow(this)" title="Supprimer">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"
                                        viewBox="0 0 24 24">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="ao-ai-add ao-ai-add--con"
                        onclick="addRow('inconvenientsList','inconvenients[]','Ex : Payant, Lent…')">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Ajouter un inconvénient
                    </button>
                </div>
            </div>

            <div class="ao-step-actions">
                <button type="button" class="ao-btn-prev" onclick="goStep(1)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    Précédent
                </button>
                <button type="button" class="ao-btn-next" onclick="goStep(3)">
                    Suivant
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ -->
        <!-- STEP 3 : Performance                      -->
        <!-- ══════════════════════════════════════════ -->
        <div class="ao-card ao-card-hidden" id="step3">

            <div class="ao-card-header">
                <div class="ao-card-icon ao-card-icon--blue">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                    </svg>
                </div>
                <div>
                    <h2 class="ao-card-title">Performance</h2>
                    <p class="ao-card-sub">Optionnel — notez les métriques de 0 à 10</p>
                </div>
            </div>

            <div class="ao-perf-grid">
                <?php
                $perfLabels = [
                    'rapidite' => ['label' => 'Rapidité', 'icon' => '⚡', 'color' => '#f59e0b'],
                    'qualite' => ['label' => 'Qualité', 'icon' => '⭐', 'color' => '#8b5cf6'],
                    'qualite_image' => ['label' => 'Qualité image', 'icon' => '🖼️', 'color' => '#06b6d4'],
                    'credibilite' => ['label' => 'Crédibilité', 'icon' => '🛡️', 'color' => '#10b981'],
                    'score_global' => ['label' => 'Score global', 'icon' => '🏆', 'color' => '#ef4444'],
                ];
                foreach ($perfLabels as $key => $meta): ?>
                    <div class="ao-perf-field">
                        <label class="ao-label">
                            <span class="ao-perf-icon"><?= $meta['icon'] ?></span>
                            <?= $meta['label'] ?>
                        </label>
                        <div class="ao-perf-input-wrap">
                            <input type="number" name="perf_<?= $key ?>"
                                value="<?= htmlspecialchars($old['perf_' . $key] ?? '') ?>" min="0" max="10" step="0.1"
                                placeholder="0 – 10"
                                class="ao-input ao-perf-input <?= isset($errors['perf_' . $key]) ? 'ao-input-err' : '' ?>"
                                oninput="clearErr(this);updatePerfBar('bar_<?= $key ?>',this.value)"
                                style="--perf-accent:<?= $meta['color'] ?>">
                            <div class="ao-perf-bar-wrap">
                                <div class="ao-perf-bar" id="bar_<?= $key ?>"
                                    style="width:<?= isset($old['perf_' . $key]) && is_numeric($old['perf_' . $key]) ? ($old['perf_' . $key] * 10) . '%' : '0%' ?>;background:<?= $meta['color'] ?>">
                                </div>
                            </div>
                        </div>
                        <?php if (isset($errors['perf_' . $key])): ?>
                            <div class="ao-err"><?= $errors['perf_' . $key] ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ao-step-actions">
                <button type="button" class="ao-btn-prev" onclick="goStep(2)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    Précédent
                </button>
                <button type="button" class="ao-btn-next" onclick="goStep(4)">
                    Suivant
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ -->
        <!-- STEP 4 : Caractéristiques                 -->
        <!-- ══════════════════════════════════════════ -->
        <div class="ao-card ao-card-hidden" id="step4">

            <div class="ao-card-header">
                <div class="ao-card-icon ao-card-icon--purple">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                    </svg>
                </div>
                <div>
                    <h2 class="ao-card-title">Caractéristiques</h2>
                    <p class="ao-card-sub">Optionnel — fonctionnalités ou specs techniques</p>
                </div>
            </div>

            <div id="carList">
                <?php
                $carNamesOld = $old['car_name'] ?? [''];
                $carDescsOld = $old['car_desc'] ?? [''];
                $carStatsOld = $old['car_statut'] ?? ['actif'];
                foreach ($carNamesOld as $i => $cn): ?>
                    <div class="ao-car-row" id="carRow<?= $i ?>">
                        <div class="ao-car-row-inner">
                            <div class="ao-car-fields">
                                <div class="ao-field" style="margin-bottom:0">
                                    <label class="ao-label">Nom</label>
                                    <input type="text" name="car_name[]" value="<?= htmlspecialchars($cn) ?>"
                                        placeholder="Ex : Multilingue, Temps réel…"
                                        class="ao-input <?= isset($errors['car_name_' . $i]) ? 'ao-input-err' : '' ?>">
                                    <?php if (isset($errors['car_name_' . $i])): ?>
                                        <div class="ao-err"><?= $errors['car_name_' . $i] ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ao-field" style="margin-bottom:0">
                                    <label class="ao-label">Description</label>
                                    <input type="text" name="car_desc[]"
                                        value="<?= htmlspecialchars($carDescsOld[$i] ?? '') ?>"
                                        placeholder="Brève description de la caractéristique" class="ao-input">
                                </div>
                                <div class="ao-field ao-field-xs" style="margin-bottom:0">
                                    <label class="ao-label">Statut</label>
                                    <div class="ao-select-wrap">
                                        <select name="car_statut[]" class="ao-input ao-select">
                                            <option value="actif" <?= ($carStatsOld[$i] ?? 'actif') === 'actif' ? 'selected' : '' ?>>Actif</option>
                                            <option value="inactif" <?= ($carStatsOld[$i] ?? '') === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                                        </select>
                                        <svg class="ao-select-icon" width="14" height="14" fill="none" stroke="currentColor"
                                            stroke-width="2" viewBox="0 0 24 24">
                                            <polyline points="6 9 12 15 18 9" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="ao-car-remove" onclick="removeCarRow(this)" title="Supprimer">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"
                                    viewBox="0 0 24 24">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="ao-car-add" onclick="addCarRow()">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Ajouter une caractéristique
            </button>

            <div class="ao-step-actions">
                <button type="button" class="ao-btn-prev" onclick="goStep(3)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    Précédent
                </button>
                <button type="submit" class="ao-btn-submit" id="submitBtn">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <line x1="22" y1="2" x2="11" y2="13" />
                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                    </svg>
                    Soumettre l'outil
                </button>
            </div>
        </div>

    </form>
</div>

<style>
    /* ── Reset / Base ─────────────────────────────── */
    body {
        margin: 0;
        min-height: 100vh;
        font-family: 'Segoe UI', Roboto, sans-serif;
        color: #1E293B;
        background: #FAF9F6;
        background-image:
            radial-gradient(circle at 80% 20%, rgba(255, 217, 0, .1), transparent 40%),
            radial-gradient(circle at 15% 50%, rgba(250, 237, 205, .7), transparent 45%),
            radial-gradient(circle at 50% 80%, rgba(241, 245, 249, 1), transparent 50%);
        background-attachment: fixed;
        position: relative;
    }

    body::before {
        content: "";
        position: absolute;
        top: 0;
        left: 25%;
        right: 25%;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(245, 184, 0, .4), transparent);
        pointer-events: none;
    }

    /* ── Wrapper ──────────────────────────────────── */
    .ao-wrapper {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 10px 20px 60px;
    }

    /* ── Breadcrumb ───────────────────────────────── */
    .ao-breadcrumb {
        width: 100%;
        max-width: 760px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }

    .ao-back {
        text-decoration: none;
        color: var(--muted);
        display: flex;
        align-items: center;
        gap: 5px;
        font-weight: 600;
        transition: .2s;
    }

    .ao-back:hover {
        color: var(--primary);
    }

    .ao-current {
        font-weight: 800;
        color: var(--navy);
    }

    /* ── Hero ─────────────────────────────────────── */
    .ao-hero {
        text-align: center;
        margin-bottom: 28px;
    }

    .ao-title {
        font-size: 32px;
        font-weight: 900;
        letter-spacing: -1px;
        color: var(--navy);
        margin-bottom: 8px;
    }

    /* ── Notice ───────────────────────────────────── */
    .ao-notice {
        width: 100%;
        max-width: 760px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        border-radius: 14px;
        background: linear-gradient(135deg, #FFF7D6, #FFF1B8);
        color: #92400E;
        margin-bottom: 20px;
        box-shadow: 0 8px 25px rgba(245, 184, 0, .15);
    }

    /* ── Alert global error ───────────────────────── */
    .ao-alert-err {
        width: 100%;
        max-width: 760px;
        padding: 14px 18px;
        border-radius: 14px;
        background: #FEF2F2;
        border: 1px solid #FECACA;
        color: #DC2626;
        font-weight: 600;
        margin-bottom: 20px;
    }

    /* ── Steps nav ────────────────────────────────── */
    .ao-steps {
        width: 100%;
        max-width: 760px;
        display: flex;
        align-items: center;
        gap: 0;
        margin-bottom: 24px;
    }

    .ao-step {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 12px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: #94A3B8;
        transition: .2s;
        white-space: nowrap;
    }

    .ao-step.active {
        color: #1B2A4A;
        background: rgba(245, 184, 0, .15);
    }

    .ao-step.done {
        color: #16a34a;
    }

    .ao-step-num {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #E2E8F0;
        color: #64748B;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 800;
        flex-shrink: 0;
        transition: .2s;
    }

    .ao-step.active .ao-step-num {
        background: #F5B800;
        color: #fff;
    }

    .ao-step.done .ao-step-num {
        background: #16a34a;
        color: #fff;
    }

    .ao-step-line {
        flex: 1;
        height: 2px;
        background: #E2E8F0;
        min-width: 20px;
    }

    /* ── Card ─────────────────────────────────────── */
    .ao-card {
        width: 100%;
        max-width: 760px;
        padding: 34px;
        border-radius: 24px;
        background: rgba(255, 255, 255, .92);
        backdrop-filter: blur(16px);
        border: 2px solid #1B2A4A;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .08), 0 4px 16px rgba(0, 0, 0, .03);
        animation: fadeUp .45s ease;
    }

    .ao-card-hidden {
        display: none;
    }

    /* Card header */
    .ao-card-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 1px solid #EEF2F7;
    }

    .ao-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #F5B800, #FFD95E);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex-shrink: 0;
        box-shadow: 0 8px 20px rgba(245, 184, 0, .25);
    }

    .ao-card-icon--green {
        background: linear-gradient(135deg, #16a34a, #4ade80);
        box-shadow: 0 8px 20px rgba(22, 163, 74, .2);
    }

    .ao-card-icon--blue {
        background: linear-gradient(135deg, #2563eb, #60a5fa);
        box-shadow: 0 8px 20px rgba(37, 99, 235, .2);
    }

    .ao-card-icon--purple {
        background: linear-gradient(135deg, #7c3aed, #a78bfa);
        box-shadow: 0 8px 20px rgba(124, 58, 237, .2);
    }

    .ao-card-title {
        font-size: 18px;
        font-weight: 800;
        color: #1B2A4A;
        margin: 0 0 4px;
    }

    .ao-card-sub {
        font-size: 13px;
        color: #94A3B8;
        margin: 0;
    }

    /* ── Fields ───────────────────────────────────── */
    .ao-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .ao-field {
        display: flex;
        flex-direction: column;
        margin-bottom: 22px;
    }

    .ao-field-sm {
        max-width: 220px;
    }

    .ao-field-xs {
        max-width: 140px;
    }

    .ao-label {
        font-size: 12px;
        font-weight: 800;
        color: #334155;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .ao-charcount {
        margin-left: auto;
        font-size: 11px;
        color: var(--muted);
        text-transform: none;
    }

    .ao-req {
        color: #EF4444;
    }

    .ao-input {
        width: 100%;
        padding: 13px 15px;
        border-radius: 14px;
        border: 1.5px solid #1B2A4A;
        background: #fff;
        font-size: 14px;
        transition: .2s;
        box-sizing: border-box;
    }

    .ao-input:hover {
        border-color: #D1D5DB;
    }

    .ao-input:focus {
        outline: none;
        border-color: #F5B800;
        box-shadow: 0 0 0 4px rgba(245, 184, 0, .15);
    }

    .ao-input::placeholder {
        color: #B8C1CC;
    }

    .ao-input-err {
        border-color: #EF4444 !important;
        background: #FFF6F6 !important;
    }

    .ao-input-err:focus {
        box-shadow: 0 0 0 4px rgba(239, 68, 68, .12);
    }

    .ao-textarea {
        resize: vertical;
        min-height: 130px;
        line-height: 1.7;
    }

    .ao-select-wrap {
        position: relative;
    }

    .ao-select {
        appearance: none;
        padding-right: 38px;
    }

    .ao-select-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94A3B8;
        pointer-events: none;
    }

    .ao-input-icon-wrap {
        position: relative;
    }

    .ao-input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94A3B8;
    }

    .ao-input-has-icon {
        padding-left: 40px;
    }

    /* ── Logo preview ─────────────────────────────── */
    .ao-logo-preview {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
        padding: 10px;
        background: #F8FAFC;
        border-radius: 12px;
        border: 1px solid #E5E7EB;
    }

    .ao-logo-preview img {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        object-fit: contain;
        background: #fff;
        border: 1px solid #E5E7EB;
    }

    /* ── Error ────────────────────────────────────── */
    .ao-err {
        margin-top: 6px;
        color: #EF4444;
        font-size: 12px;
        font-weight: 600;
    }

    /* ── Step actions ─────────────────────────────── */
    .ao-step-actions {
        margin-top: 28px;
        padding-top: 24px;
        border-top: 1px solid #EEF2F7;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ao-btn-cancel,
    .ao-btn-prev {
        padding: 12px 18px;
        border-radius: 12px;
        border: 1px solid #E5E7EB;
        text-decoration: none;
        color: #64748B;
        font-weight: 700;
        transition: .2s;
        display: flex;
        align-items: center;
        gap: 6px;
        background: transparent;
        cursor: pointer;
        font-size: 14px;
    }

    .ao-btn-cancel:hover,
    .ao-btn-prev:hover {
        border-color: #CBD5E1;
        background: #c4c7ca;
    }

    .ao-btn-next {
        padding: 13px 24px;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        font-weight: 800;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #1B2A4A, #2d4a7a);
        box-shadow: 0 10px 30px rgba(27, 42, 74, .25);
        transition: .2s;
        font-size: 14px;
    }

    .ao-btn-next:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px rgba(15, 23, 42, .30);
    }

    .ao-btn-submit {
        padding: 13px 28px;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        font-weight: 800;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #1B2A4A, #1B2A4A);
        box-shadow: 0 10px 30px rgba(68, 98, 168, .25);
        transition: .2s;
        font-size: 14px;
    }

    .ao-btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px rgba(15, 23, 42, .30);
    }

    .ao-btn-submit:disabled {
        opacity: .7;
        cursor: not-allowed;
    }

    /* ── Avantages / Inconvénients ────────────────── */
    .ao-ai-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    .ao-ai-col {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .ao-ai-col--pro {}

    .ao-ai-col--con {}

    .ao-ai-col-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 800;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 4px;
    }

    .ao-ai-row {
        display: flex;
        align-items: center;
        gap: 8px;
        animation: fadeUp .25s ease;
    }

    .ao-ai-row .ao-input {
        flex: 1;
        margin-bottom: 0;
        padding: 10px 14px;
    }

    .ao-ai-remove {
        flex-shrink: 0;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: 1px solid #E5E7EB;
        background: #F8FAFC;
        color: #94A3B8;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: .2s;
    }

    .ao-ai-remove:hover {
        background: #FEF2F2;
        border-color: #FECACA;
        color: #DC2626;
    }

    .ao-ai-add {
        margin-top: 4px;
        padding: 9px 14px;
        border-radius: 10px;
        border: 1.5px dashed;
        font-size: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        background: transparent;
        transition: .2s;
        align-self: flex-start;
    }

    .ao-ai-add--pro {
        border-color: #86efac;
        color: #16a34a;
    }

    .ao-ai-add--pro:hover {
        background: #f0fdf4;
    }

    .ao-ai-add--con {
        border-color: #fca5a5;
        color: #dc2626;
    }

    .ao-ai-add--con:hover {
        background: #fef2f2;
    }

    /* ── Performance ──────────────────────────────── */
    .ao-perf-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }

    .ao-perf-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .ao-perf-icon {
        font-size: 16px;
    }

    .ao-perf-input-wrap {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .ao-perf-input {
        text-align: center;
        font-size: 20px;
        font-weight: 800;
        padding: 14px 10px !important;
    }

    .ao-perf-input:focus {
        border-color: var(--perf-accent) !important;
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--perf-accent) 20%, transparent) !important;
    }

    .ao-perf-bar-wrap {
        height: 6px;
        background: #EEF2F7;
        border-radius: 6px;
        overflow: hidden;
    }

    .ao-perf-bar {
        height: 100%;
        border-radius: 6px;
        transition: width .4s cubic-bezier(.4, 0, .2, 1);
    }

    /* ── Caractéristiques ─────────────────────────── */
    .ao-car-row {
        background: #F8FAFC;
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        padding: 18px;
        margin-bottom: 12px;
        animation: fadeUp .25s ease;
    }

    .ao-car-row-inner {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .ao-car-fields {
        flex: 1;
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 14px;
    }

    .ao-car-remove {
        flex-shrink: 0;
        width: 34px;
        height: 34px;
        margin-top: 24px;
        border-radius: 10px;
        border: 1px solid #E5E7EB;
        background: #fff;
        color: #94A3B8;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: .2s;
    }

    .ao-car-remove:hover {
        background: #FEF2F2;
        border-color: #FECACA;
        color: #DC2626;
    }

    .ao-car-add {
        width: 100%;
        padding: 13px;
        border-radius: 14px;
        border: 2px dashed #CBD5E1;
        background: transparent;
        font-size: 13px;
        font-weight: 700;
        color: #64748B;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        transition: .2s;
        margin-top: 4px;
    }

    .ao-car-add:hover {
        border-color: #7c3aed;
        color: #7c3aed;
        background: rgba(124, 58, 237, .04);
    }

    /* ── Animation ────────────────────────────────── */
    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(16px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes ao-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ── Mobile ───────────────────────────────────── */
    @media (max-width: 768px) {

        .ao-row-2,
        .ao-ai-grid {
            grid-template-columns: 1fr;
        }

        .ao-car-fields {
            grid-template-columns: 1fr;
        }

        .ao-card {
            padding: 22px;
        }

        .ao-step-actions {
            flex-direction: column;
            gap: 12px;
        }

        .ao-btn-cancel,
        .ao-btn-prev,
        .ao-btn-next,
        .ao-btn-submit {
            width: 100%;
            justify-content: center;
        }

        .ao-field-sm,
        .ao-field-xs {
            max-width: 100%;
        }

        .ao-steps {
            flex-wrap: wrap;
            gap: 4px;
        }

        .ao-step-line {
            display: none;
        }

        .ao-step-label {
            display: none;
        }

        .ao-perf-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* ── Sélection de modèles ─────────────────────── */
    .ao-models-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 14px;
        border: 1.5px solid #1B2A4A;
        border-radius: 14px;
        background: #fff;
        max-height: 220px;
        overflow-y: auto;
    }

    .ao-model-item {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 12px;
        border-radius: 99px;
        border: 1.5px solid #E2E8F0;
        background: #F8FAFC;
        cursor: pointer;
        transition: border-color .15s, background .15s;
        user-select: none;
    }

    .ao-model-item:hover {
        border-color: #F5B800;
        background: #FFFBEB;
    }

    .ao-model-item input[type="checkbox"] {
        width: 15px;
        height: 15px;
        accent-color: #F5B800;
        cursor: pointer;
        flex-shrink: 0;
    }

    .ao-model-item:has(input:checked) {
        border-color: #F5B800;
        background: #FFFBEB;
    }

    .ao-model-label {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .ao-model-provider {
        font-size: 10px;
        font-weight: 700;
        color: #94A3B8;
        background: #E2E8F0;
        border-radius: 4px;
        padding: 1px 5px;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    .ao-field-hint {
        font-size: 11px;
        color: #94A3B8;
        margin: 6px 0 0;
    }
</style>

<script>
    // ── Step navigation ────────────────────────────────────────────
    var currentStep = 1;

    function goStep(n) {
        document.getElementById('step' + currentStep).classList.add('ao-card-hidden');
        document.getElementById('step' + n).classList.remove('ao-card-hidden');

        // Update nav
        document.querySelectorAll('.ao-step').forEach(function (btn) {
            var s = parseInt(btn.dataset.step);
            btn.classList.remove('active', 'done');
            if (s === n) btn.classList.add('active');
            if (s < n) btn.classList.add('done');
        });

        currentStep = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── Step 1 validation before next ─────────────────────────────
    function validateStep1() {
        var nom = document.querySelector('[name="nom"]');
        var desc = document.querySelector('[name="description"]');
        var cat = document.querySelector('[name="id_categorie"]');
        var ok = true;

        if (nom.value.trim().length < 2) {
            showInlineErr(nom, 'Au moins 2 caractères.');
            ok = false;
        }
        if (desc.value.trim().length < 10) {
            showInlineErr(desc, 'Au moins 10 caractères.');
            ok = false;
        }
        if (!cat.value) {
            showInlineErr(cat, 'Veuillez choisir une catégorie.');
            ok = false;
        }
        if (ok) goStep(2);
    }

    function showInlineErr(el, msg) {
        el.classList.add('ao-input-err');
        var field = el.closest('.ao-field') || el.parentElement.closest('.ao-field');
        if (!field) return;
        if (!field.querySelector('.ao-err')) {
            var d = document.createElement('div');
            d.className = 'ao-err';
            d.textContent = msg;
            field.appendChild(d);
        }
        el.focus();
    }

    // ── Helpers ────────────────────────────────────────────────────
    function clearErr(el) {
        el.classList.remove('ao-input-err');
        var parent = el.closest('.ao-field') || el.parentElement.closest('.ao-field');
        if (parent) { var e = parent.querySelector('.ao-err'); if (e) e.remove(); }
    }
    function updateCount(el, max, id) {
        var n = el.value.length;
        var s = document.getElementById(id);
        if (!s) return;
        s.textContent = n + '/' + max;
        s.style.color = n > max * 0.9 ? '#D97706' : 'var(--muted)';
    }
    function previewLogo(url) {
        var p = document.getElementById('logoPreview'), img = document.getElementById('logoImg');
        if (url && url.startsWith('http')) { img.src = url; p.style.display = 'flex'; }
        else p.style.display = 'none';
    }
    function updatePerfBar(barId, val) {
        var bar = document.getElementById(barId);
        if (!bar) return;
        var pct = Math.min(Math.max(parseFloat(val) || 0, 0), 10) * 10;
        bar.style.width = pct + '%';
    }

    // ── Avantages / Inconvénients dynamic rows ─────────────────────
    function addRow(listId, name, placeholder) {
        var list = document.getElementById(listId);
        var div = document.createElement('div');
        div.className = 'ao-ai-row';
        div.innerHTML =
            '<input type="text" name="' + name + '" placeholder="' + placeholder + '" class="ao-input">' +
            '<button type="button" class="ao-ai-remove" onclick="removeRow(this)" title="Supprimer">' +
            '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">' +
            '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
        list.appendChild(div);
        div.querySelector('input').focus();
    }
    function removeRow(btn) {
        var row = btn.closest('.ao-ai-row');
        if (row) row.remove();
    }

    // ── Caractéristiques dynamic rows ─────────────────────────────
    var carIdx = <?= count($carNamesOld ?? ['']) ?>;
    function addCarRow() {
        var list = document.getElementById('carList');
        var div = document.createElement('div');
        div.className = 'ao-car-row';
        div.innerHTML =
            '<div class="ao-car-row-inner">' +
            '<div class="ao-car-fields">' +
            '<div class="ao-field" style="margin-bottom:0">' +
            '<label class="ao-label">Nom</label>' +
            '<input type="text" name="car_name[]" placeholder="Ex : Multilingue…" class="ao-input"></div>' +
            '<div class="ao-field" style="margin-bottom:0">' +
            '<label class="ao-label">Description</label>' +
            '<input type="text" name="car_desc[]" placeholder="Brève description" class="ao-input"></div>' +
            '<div class="ao-field ao-field-xs" style="margin-bottom:0">' +
            '<label class="ao-label">Statut</label>' +
            '<div class="ao-select-wrap">' +
            '<select name="car_statut[]" class="ao-input ao-select">' +
            '<option value="actif">Actif</option><option value="inactif">Inactif</option></select>' +
            '<svg class="ao-select-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>' +
            '</div></div></div>' +
            '<button type="button" class="ao-car-remove" onclick="removeCarRow(this)" title="Supprimer">' +
            '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">' +
            '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>';
        list.appendChild(div);
        div.querySelector('input').focus();
        carIdx++;
    }
    function removeCarRow(btn) {
        var row = btn.closest('.ao-car-row');
        if (row) row.remove();
    }

    // ── Init ───────────────────────────────────────────────────────
    (function () {
        var ta = document.querySelector('[name="description"]');
        if (ta) updateCount(ta, 500, 'descCount');

        // If PHP returned errors, show the right step
        var hasStep1Err = <?= json_encode(!empty($errors['nom']) || !empty($errors['description']) || !empty($errors['id_categorie']) || !empty($errors['url']) || !empty($errors['logo_url']) || !empty($errors['version'])) ?>;
        var hasStep3Err = <?= json_encode(!empty(array_filter(array_keys($errors), fn($k) => str_starts_with($k, 'perf_')))) ?>;
        if (hasStep1Err) { goStep(1); }
        else if (hasStep3Err) { goStep(3); }
        else if (<?= json_encode(!empty($errors)) ?>) { goStep(4); }
    })();

    // ── Anti double-submit ─────────────────────────────────────────
    document.getElementById('addOutilForm').addEventListener('submit', function () {
        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="animation:ao-spin 1s linear infinite"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0"/></svg> Envoi en cours…';
    });
</script>

<?php require 'layout_end.php'; ?>