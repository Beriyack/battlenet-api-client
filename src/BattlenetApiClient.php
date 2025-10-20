<?php
namespace App;

use Beriyack\Storage;
use Exception;

/**
 * Client pour interagir avec l'API Battle.net de Blizzard.
 * Gère l'authentification OAuth (Client Credentials), la mise en cache du token et des réponses.
 */
class BattlenetApiClient
{
    private string $clientId;
    private string $clientSecret;
    private string $region;
    private string $cacheDir;
    private ?string $accessToken = null;
    private ?string $caCertPath;

    /**
     * Contient la source de la dernière requête effectuée par la méthode get().
     * Peut être 'cache' ou 'api'.
     */
    public ?string $lastRequestSource = null;

    private const TOKEN_CACHE_KEY = 'battlenet_access_token';

    /**
     * Construit une nouvelle instance du client API.
     *
     * @param string $clientId Votre ID client Battle.net.
     * @param string $clientSecret Votre secret client Battle.net.
     * @param string $region La région de l'API à utiliser (ex: 'us', 'eu', 'kr', 'tw').
     * @param string $cacheDir Le répertoire pour stocker les fichiers de cache.
     * @param string|null $caCertPath Chemin optionnel vers un fichier de certificat CA.
     */
    public function __construct(string $clientId, string $clientSecret, string $region, string $cacheDir, ?string $caCertPath = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->region = $region;
        $this->cacheDir = $cacheDir;
        $this->caCertPath = $caCertPath;

        if ($this->caCertPath && !file_exists($this->caCertPath)) {
            throw new Exception("Le fichier de certificat CA '{$this->caCertPath}' est introuvable.");
        }

        // S'assure que le répertoire de cache existe.
        Storage::makeDirectory($this->cacheDir);
    }

    /**
     * Récupère le token d'accès, soit depuis le cache, soit en en demandant un nouveau.
     *
     * @return string Le token d'accès.
     * @throws Exception Si l'authentification échoue.
     */
    private function getAccessToken(): string
    {
        $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . self::TOKEN_CACHE_KEY . '.json';

        // 1. Essayer de charger depuis le cache
        if (Storage::exists($cacheFile)) {
            $data = json_decode(Storage::get($cacheFile), true);
            // Vérifie si le token est toujours valide (avec une marge de 60 secondes)
            if (isset($data['access_token']) && isset($data['expires_at']) && $data['expires_at'] > (time() + 60)) {
                $this->accessToken = $data['access_token'];
                return $this->accessToken;
            }
        }

        // 2. Si non trouvé ou expiré, demander un nouveau token
        $tokenUrl = 'https://oauth.battle.net/token'; // L'URL du token OAuth est fixe pour la plupart des régions.
        
        $curlOptions = [];
        // L'authentification Basic (client_id:client_secret) via CURLOPT_USERPWD
        $curlOptions[CURLOPT_USERPWD] = $this->clientId . ':' . $this->clientSecret;

        if ($this->caCertPath) {
            $curlOptions[CURLOPT_CAINFO] = $this->caCertPath;
        }

        // Utiliser ApiClient pour la requête d'authentification en spécifiant le bon Content-Type
        $client = new \Beriyack\Client\ApiClient($tokenUrl);
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $response = $client->post('', ['grant_type' => 'client_credentials'], $headers, $curlOptions);

        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $expiresIn = $response['expires_in'] ?? 3600; // Durée de vie en secondes

            // Sauvegarder le token et sa date d'expiration dans le cache
            $cacheData = [
                'access_token' => $this->accessToken,
                'expires_at' => time() + $expiresIn
            ];
            Storage::put($cacheFile, json_encode($cacheData));

            return $this->accessToken;
        }

        // Si le token n'est pas dans la réponse, cela signifie une erreur de l'API
        $errorMessage = $response['error_description'] ?? json_encode($response);
        throw new Exception('Impossible d\'obtenir le token d\'accès Battle.net. Réponse : ' . $errorMessage);
    }

    /**
     * Exécute une requête GET sur un endpoint de l'API Battle.net.
     *
     * @param string $endpoint Le chemin de l'API (ex: '/data/wow/token/').
     * @param array $params Les paramètres de la requête (ex: ['namespace' => 'dynamic-us']).
     * @param int $cacheDuration Durée de vie du cache pour cette requête en secondes.
     * @return array La réponse de l'API décodée.
     * @throws Exception Si la requête échoue.
     */
    public function get(string $endpoint, array $params = [], int $cacheDuration = 3600): array
    {
        $cacheKey = 'battlenet_request_' . sha1($endpoint . http_build_query($params));
        $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';

        // 1. Essayer de charger la réponse depuis le cache
        if (Storage::exists($cacheFile) && (time() - Storage::lastModified($cacheFile)) < $cacheDuration) {
            $this->lastRequestSource = 'cache';
            return json_decode(Storage::get($cacheFile), true);
        }

        // 2. Exécuter la requête API
        $apiUrl = sprintf('https://%s', $this->getApiHost());
        $headers = ['Authorization' => 'Bearer ' . $this->getAccessToken()];
        
        $curlOptions = [];
        if ($this->caCertPath) {
            $curlOptions[CURLOPT_CAINFO] = $this->caCertPath;
        }

        $client = new \Beriyack\Client\ApiClient($apiUrl, $headers); // On initialise le client avec l'URL et les headers
        $response = $client->get($endpoint, $params, $curlOptions); // On passe les options cURL à la méthode get()

        $this->lastRequestSource = 'api';
        // 3. Mettre la réponse en cache
        Storage::put($cacheFile, json_encode($response));

        return $response;
    }

    /**
     * Retourne l'hôte de l'API Battle.net en fonction de la région.
     *
     * @return string L'hôte de l'API.
     */
    private function getApiHost(): string
    {
        return ($this->region === 'cn') ? 'gateway.battlenet.com.cn' : $this->region . '.api.blizzard.com';
    }

    /**
     * Récupère l'index de toutes les mascottes de combat de World of Warcraft.
     *
     * @param string $locale La locale pour les noms des mascottes (ex: 'en_US', 'fr_FR').
     * @return array La liste des mascottes.
     * @throws Exception
     */
    public function pets(string $locale = 'en_US'): array
    {
        return $this->get('/data/wow/pet/index', [
            'namespace' => 'static-' . $this->region,
            'locale'    => $locale
        ]);
    }

    /**
     * Récupère les détails d'une mascotte de combat spécifique par son ID.
     *
     * @param int $petId L'ID de la mascotte à récupérer.
     * @param string $locale La locale pour les noms et descriptions (ex: 'en_US', 'fr_FR').
     * @return array Les détails de la mascotte.
     * @throws Exception
     */
    public function petDetails(int $petId, string $locale = 'en_US'): array
    {
        return $this->get("/data/wow/pet/{$petId}", [
            'namespace' => 'static-' . $this->region,
            'locale'    => $locale
        ]);
    }

    /**
     * Récupère les médias (comme l'icône de la créature) pour une mascotte de combat spécifique.
     *
     * @param int $petId L'ID de la mascotte.
     * @return array Les données des médias.
     * @throws Exception
     */
    public function petMedia(int $petId): array
    {
        return $this->get("/data/wow/media/pet/{$petId}", [
            'namespace' => 'static-' . $this->region,
        ]);
    }

    /**
     * Récupère l'index de toutes les techniques de mascottes de combat.
     *
     * @param string $locale La locale pour les noms des techniques (ex: 'en_US', 'fr_FR').
     * @return array La liste des techniques.
     * @throws Exception
     */
    public function petAbilitiesIndex(string $locale = 'en_US'): array
    {
        return $this->get('/data/wow/pet-ability/index', [
            'namespace' => 'static-' . $this->region,
            'locale'    => $locale
        ]);
    }

    /**
     * Récupère les détails d'une technique de mascotte de combat spécifique.
     *
     * @param int $petAbilityId L'ID de la technique.
     * @param string $locale La locale pour le nom et la description (ex: 'en_US', 'fr_FR').
     * @return array Les détails de la technique.
     * @throws Exception
     */
    public function petAbilityDetails(int $petAbilityId, string $locale = 'en_US'): array
    {
        return $this->get("/data/wow/pet-ability/{$petAbilityId}", [
            'namespace' => 'static-' . $this->region,
            'locale'    => $locale
        ]);
    }

    /**
     * Récupère les médias pour une technique de mascotte de combat spécifique.
     *
     * @param int $petAbilityId L'ID de la technique.
     * @return array Les données des médias.
     * @throws Exception
     */
    public function petAbilityMedia(int $petAbilityId): array
    {
        return $this->get("/data/wow/media/pet-ability/{$petAbilityId}", [
            'namespace' => 'static-' . $this->region,
        ]);
    }
}