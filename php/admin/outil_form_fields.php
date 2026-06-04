<?php
/* outil_form_fields.php — champs partagés Add/Edit
   Variables attendues :
     $isEdit  (bool)  → true depuis le modal Modifier
     $categories  (array PDO)
     $pdo  (PDO)
*/
$prefix = isset($isEdit) && $isEdit ? 'e_' : '';

// Charger tous les modèles actifs
$allModels = $pdo->query(
    "SELECT m.ID_MODEL, m.name, p.name AS provider_name
     FROM models m
     LEFT JOIN providers p ON m.ID_PROVIDERS = p.ID_PROVIDERS
     WHERE m.status = 'actif'
     ORDER BY p.name, m.name"
)->fetchAll();

// Si mode édition, charger les modèles déjà liés à l'outil
$linkedModels = [];
if (isset($isEdit) && $isEdit && isset($pid) && $pid > 0) {
    $lm = $pdo->prepare(
        "SELECT ID_MODEL FROM tool_models WHERE ID_OUTILS_IA = ?"
    );
    $lm->execute([$pid]);
    $linkedModels = array_column($lm->fetchAll(), 'ID_MODEL');
}
?>

<style>
/* ── Image preview ───────────────────────────────────── */
.img-preview-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 6px;
}
.img-preview {
    width: 56px;
    height: 56px;
    object-fit: contain;
    border: 1px solid var(--adm-border, #e2e8f0);
    border-radius: 10px;
    background: #f8fafc;
    padding: 4px;
    display: none; /* caché si URL vide */
}
.img-preview-placeholder {
    width: 56px;
    height: 56px;
    border: 1.5px dashed var(--adm-border, #cbd5e1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--adm-muted, #94a3b8);
    font-size: .7rem;
    text-align: center;
    line-height: 1.3;
    padding: 4px;
}

/* ── Modèles checkbox grid ──────────────────────────── */
.models-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
    max-height: 220px;
    overflow-y: auto;
    padding: 4px 2px;
}
.model-cb-label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    border: 1px solid var(--adm-border, #e2e8f0);
    border-radius: 8px;
    cursor: pointer;
    font-size: .82rem;
    transition: background .15s, border-color .15s;
    line-height: 1.3;
}
.model-cb-label:hover {
    background: var(--adm-hover, #f1f5f9);
    border-color: var(--adm-blue, #3b82f6);
}
.model-cb-label input[type="checkbox"] {
    accent-color: var(--adm-blue, #3b82f6);
    width: 15px;
    height: 15px;
    flex-shrink: 0;
}
.model-provider-tag {
    font-size: .68rem;
    color: var(--adm-muted, #64748b);
    display: block;
}
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: .8rem; font-weight: 600; margin-bottom: 5px; color: var(--adm-text, #1e293b); }
.form-control {
    width: 100%; padding: 8px 10px;
    border: 1px solid var(--adm-border, #e2e8f0);
    border-radius: 8px; font-size: .875rem;
    background: var(--adm-surface, #fff);
    color: var(--adm-text, #1e293b);
    box-sizing: border-box;
}
.form-control:focus { outline: none; border-color: var(--adm-blue, #3b82f6); }
</style>

<!-- Nom -->
<div class="form-group">
    <label class="form-label" for="<?= $prefix ?>nom">Nom de l'outil *</label>
    <input class="form-control" type="text" id="<?= $prefix ?>nom" name="nom" required
           placeholder="Ex : ChatGPT, Midjourney…" maxlength="150">
</div>

<!-- Description -->
<div class="form-group">
    <label class="form-label" for="<?= $prefix ?>description">Description</label>
    <textarea class="form-control" id="<?= $prefix ?>description" name="description"
              rows="3" placeholder="Décrivez brièvement l'outil…"></textarea>
</div>

<!-- Logo URL + prévisualisation -->
<div class="form-group">
    <label class="form-label" for="<?= $prefix ?>logo_url">URL du logo</label>
    <input class="form-control" type="url" id="<?= $prefix ?>logo_url" name="logo_url"
           placeholder="https://exemple.com/logo.png"
           oninput="previewImage(this, '<?= $prefix ?>preview')">
    <div class="img-preview-wrap">
        <img id="<?= $prefix ?>preview" class="img-preview" alt="Aperçu logo"
             onerror="this.style.display='none';document.getElementById('<?= $prefix ?>ph').style.display='flex'">
        <div id="<?= $prefix ?>ph" class="img-preview-placeholder">Aperçu image</div>
    </div>
</div>

<!-- URL du site -->
<div class="form-group">
    <label class="form-label" for="<?= $prefix ?>url">URL du site</label>
    <input class="form-control" type="url" id="<?= $prefix ?>url" name="url"
           placeholder="https://…">
</div>

<!-- Version -->
<div class="form-group">
    <label class="form-label" for="<?= $prefix ?>version">Version</label>
    <input class="form-control" type="number" id="<?= $prefix ?>version" name="version"
           step="0.01" min="0" placeholder="Ex : 4.00">
</div>

<!-- Catégorie -->
<div class="form-group">
    <label class="form-label" for="<?= $prefix ?>ID_CATEGORIE">Catégorie</label>
    <select class="form-control" id="<?= $prefix ?>ID_CATEGORIE" name="ID_CATEGORIE">
        <option value="">— Sélectionner —</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['ID_CATEGORIE'] ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Modèles associés (nouveau) -->
<div class="form-group">
    <label class="form-label">Modèles associés</label>
    <?php if (empty($allModels)): ?>
        <p style="font-size:.8rem;color:var(--adm-muted)">Aucun modèle actif disponible.</p>
    <?php else: ?>
        <div class="models-grid">
            <?php foreach ($allModels as $m): ?>
                <?php $checked = in_array($m['ID_MODEL'], $linkedModels) ? 'checked' : ''; ?>
                <label class="model-cb-label">
                    <input type="checkbox" name="model_ids[]"
                           value="<?= $m['ID_MODEL'] ?>" <?= $checked ?>>
                    <span>
                        <?= htmlspecialchars($m['name']) ?>
                        <span class="model-provider-tag"><?= htmlspecialchars($m['provider_name'] ?? '—') ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function previewImage(input, previewId) {
    var img = document.getElementById(previewId);
    var ph  = document.getElementById(previewId.replace('preview','ph'));
    var url = input.value.trim();
    if (!url) {
        img.style.display = 'none';
        if (ph) ph.style.display = 'flex';
        return;
    }
    img.src = url;
    img.style.display = 'block';
    if (ph) ph.style.display = 'none';
}
</script>