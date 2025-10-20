<?php

/**
 * Fichier de bootstrap pour l'application d'exemple.
 * Gère l'autoloading, la configuration et l'initialisation du client API.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\BattlenetApiClient;

// --- Configuration ---
require_once __DIR__ . '/../../../config.secret.php';

$clientId = API_BATTLENET_CLIENTID;
$clientSecret = API_BATTLENET_SECRET;
$region = 'eu'; // ou 'us', 'kr', 'tw'
$cacheDir = __DIR__ . '/cache';
$caCertPath = __DIR__ . '/Amazon Root CA 1.crt';

// Initialisation du client pour qu'il soit disponible dans toutes les pages
$battlenetClient = new BattlenetApiClient($clientId, $clientSecret, $region, $cacheDir, $caCertPath);
