<?php

require_once __DIR__ . '/layout/header.php';

// 1. Valider l'ID de la mascotte depuis l'URL
$petId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$petId) {
    echo '<div class="alert alert-danger">ID de mascotte non valide ou manquant.</div>';
    require_once __DIR__ . '/layout/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Détails de la Mascotte</h1>
    <a href="pets.php" class="btn btn-outline-secondary">Retour à la liste</a>
</div>

<?php
try {
    // 2. Appeler la nouvelle méthode pour récupérer les détails
    $details = $battlenetClient->petDetails($petId, 'fr_FR');
    $source = $battlenetClient->lastRequestSource; // On récupère la source de la donnée

    // 3. Afficher les détails dans une carte Bootstrap
    ?>
    <div class="card">
        <div class="card-footer text-muted">
            <?php if ($source === 'cache'): ?>
                <span class="badge bg-info">Données récupérées depuis le cache</span>
            <?php else: ?>
                <span class="badge bg-success">Données récupérées depuis l'API (nouvelle mise en cache)</span>
            <?php endif; ?>
        </div>
        <div class="card-header d-flex align-items-center">
            <img src="<?= htmlspecialchars($details['icon']) ?>" alt="Icône de <?= htmlspecialchars($details['name']) ?>" class="me-3">
            <h2 class="h3 mb-0"><?= htmlspecialchars($details['name']) ?></h2>
        </div>
        <div class="card-body">
            <p class="card-text"><em><?= htmlspecialchars($details['description'] ?? 'Aucune description disponible.') ?></em></p>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><strong>ID :</strong> <?= $details['id'] ?></li>
                <li class="list-group-item"><strong>Type :</strong> <?= htmlspecialchars($details['battle_pet_type']['name']) ?></li>
                <li class="list-group-item"><strong>Source :</strong> <?= htmlspecialchars($details['source']['name'] ?? 'Inconnue') ?></li>
                <li class="list-group-item"><strong>Capturable :</strong> <?= $details['is_capturable'] ? 'Oui' : 'Non' ?></li>
                <li class="list-group-item"><strong>Échangeable :</strong> <?= $details['is_tradable'] ? 'Oui' : 'Non' ?></li>
            </ul>

            <?php if (!empty($details['abilities'])): ?>
                <div class="mt-4">
                    <h5 class="card-title">Techniques</h5>
                    <div class="list-group">
                        <?php foreach ($details['abilities'] as $abilitySlot): ?>
                            <a href="#" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($abilitySlot['ability']['name']) ?></h6>
                                    <small>Niveau <?= $abilitySlot['required_level'] ?></small>
                                </div>
                                <small>ID de la technique : <?= $abilitySlot['ability']['id'] ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Une erreur est survenue lors de la récupération des détails : ' . $e->getMessage() . '</div>';
}

require_once __DIR__ . '/layout/footer.php';
?>
