<?php

require_once __DIR__ . '/layout/header.php';

// Logique pour la mise à jour manuelle du cache
$forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] == 1;
$lastUpdateCacheKey = 'pets_last_manual_update';
$lastUpdateFile = $cacheDir . DIRECTORY_SEPARATOR . $lastUpdateCacheKey;

// On vérifie si le fichier de suivi existe avant de lire sa valeur.
$lastUpdateTime = \Beriyack\Storage::exists($lastUpdateFile) ? (int)\Beriyack\Storage::get($lastUpdateFile) : 0;

$canUpdate = (time() - $lastUpdateTime) > 3600; // 3600 secondes = 1 heure

if ($forceRefresh && $canUpdate) {
    // On supprime le cache spécifique à la requête des mascottes
    $params = ['namespace' => 'static-' . $region, 'locale' => 'fr_FR'];
    $cacheKey = 'battlenet_request_' . sha1('/data/wow/pet/index' . http_build_query($params));
    $fileToDelete = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    \Beriyack\Storage::delete($fileToDelete);

    // On enregistre le moment de la mise à jour
    \Beriyack\Storage::put($cacheDir . DIRECTORY_SEPARATOR . $lastUpdateCacheKey, time());
    $lastUpdateTime = time();
    $canUpdate = false; // On désactive le bouton pour la prochaine heure
}
?>

<h1>Liste des Mascottes de World of Warcraft</h1>
<p>Cette page utilise la méthode <code>$battlenetClient->pets('fr_FR')</code> pour récupérer et afficher la liste de toutes les mascottes de combat.</p>
<p>La réponse de l'API est mise en cache. Vous pouvez forcer une mise à jour manuellement une fois par heure.</p>

<a href="pets.php?force_refresh=1" class="btn btn-primary <?= $canUpdate ? '' : 'disabled' ?>" role="button" aria-disabled="<?= $canUpdate ? 'false' : 'true' ?>">
    Mettre à jour la liste (Forcer le cache)
</a>
<?php if (!$canUpdate): ?>
    <small class="d-block mt-2 text-muted">Prochaine mise à jour manuelle possible dans <?= ceil((3600 - (time() - $lastUpdateTime)) / 60) ?> minutes.</small>
<?php endif; ?>
<hr>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nom</th>
        </tr>
    </thead>
    <tbody>
        <?php
        try {
            $petsData = $battlenetClient->pets('fr_FR');

            if (isset($petsData['pets'])) {
                foreach ($petsData['pets'] as $pet) {
                    echo "<tr>";
                    echo "<td>" . $pet['id'] . "</td>";
                    // Transforme le nom en lien vers la page de détails
                    $petName = htmlspecialchars($pet['name'] ?? 'Nom non disponible');
                    $detailsUrl = 'pet-details.php?id=' . $pet['id'];
                    echo '<td><a href="' . $detailsUrl . '">' . $petName . '</a></td>';
                    echo "</tr>";
                }
            }
        } catch (Exception $e) {
            echo '<tr><td colspan="2"><div class="alert alert-danger">Une erreur est survenue : ' . $e->getMessage() . '</div></td></tr>';
        }
        ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
