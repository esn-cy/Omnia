<?php /** @noinspection PhpUnused */

namespace Drupal\omnia\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use UnexpectedValueException;

abstract class AppleAuthControllerBase extends ControllerBase
{
    protected ClientInterface $httpClient;
    protected Settings $settings;
    protected CacheBackendInterface $cache;
    protected LoggerChannelInterface $logger;

    public function __construct(
        ClientInterface               $httpClient,
        Settings                      $settings,
        CacheBackendInterface         $cache,
        LoggerChannelFactoryInterface $loggerFactory
    )
    {
        $this->httpClient = $httpClient;
        $this->settings = $settings;
        $this->cache = $cache;
        $this->logger = $loggerFactory->get('omnia');
    }

    public static function create(ContainerInterface $container): self
    {
        /** @var ClientInterface $httpClient */
        $httpClient = $container->get('http_client');

        /** @var Settings $settings */
        $settings = $container->get('settings');

        /** @var CacheBackendInterface $cache */
        $cache = $container->get('cache.default');

        /** @var LoggerChannelFactoryInterface $loggerFactory */
        $loggerFactory = $container->get('logger.factory');

        return new static(
            $httpClient,
            $settings,
            $cache,
            $loggerFactory
        );
    }

    abstract protected function getClientID(): string;

    protected function getRedirectURI(Request $request): string
    {
        return $request->getUriForPath($request->getPathInfo());
    }

    abstract protected function onAppleAuthSuccess(
        TranslatableMarkup|string $email,
        array $payload,
        ?array $stateData,
        Request $request
    ): RedirectResponse;

    abstract protected function onAppleAuthFailure(
        TranslatableMarkup|string $errorMessage,
        ?array $stateData,
        Request $request
    ): RedirectResponse;

    /**
     * @throws GuzzleException
     */
    protected function getApplePublicKeys(bool $forceRefresh = false): array
    {
        $cid = 'omnia:apple_public_keys';
        if (!$forceRefresh && ($cached = $this->cache->get($cid))) {
            return $cached->data;
        }
        $response = $this->httpClient->request('GET', 'https://appleid.apple.com/auth/keys');
        $keys = json_decode((string) $response->getBody(), true) ?? [];
        if (!empty($keys['keys'])) {
            $this->cache->set($cid, $keys, time() + 24 * 60 * 60);
        }
        return $keys;
    }

    /**
     * @throws GuzzleException
     */
    protected function verifyToken(string $idToken): ?array
    {
        $jwks = $this->getApplePublicKeys();
        if (empty($jwks['keys'])) {
            $this->logger->error('Failed to retrieve Apple public keys.');
            return null;
        }

        try {
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($idToken, $keys);
        } catch (UnexpectedValueException) {
            $jwks = $this->getApplePublicKeys(true);
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($idToken, $keys);
        }

        $payload = (array) $decoded;

        if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
            $this->logger->error('Apple token issuer mismatch.');
            return null;
        }

        if (($payload['aud'] ?? '') !== $this->getClientID()) {
            $this->logger->error('Apple token audience mismatch.');
            return null;
        }

        if (empty($payload['email'])) {
            $this->logger->error('Apple token missing email claim.');
            return null;
        }

        return $payload;
    }

    public function callback(Request $request): RedirectResponse
    {
        $stateParam = $request->request->get('state');
        if (empty($stateParam)) {
            return $this->onAppleAuthFailure($this->t('Invalid session state. Please try again.'), null, $request);
        }

        $storedState = null;
        $decoded = base64_decode(strtr($stateParam, '-_', '+/'));
        $parts = $decoded ? explode('.', $decoded, 2) : [];

        if (count($parts) === 2) {
            [$json, $signature] = $parts;
            $expected = hash_hmac('sha256', $json, $this->settings::getHashSalt());
            if (hash_equals($expected, $signature)) {
                $data = json_decode($json, true);
                if (!empty($data['ts']) && (time() - $data['ts'] < 900)) {
                    $storedState = $data;
                }
            }
        }
        if (empty($storedState)) {
            $this->logger->warning('Apple OAuth state mismatch or expired.');
            return $this->onAppleAuthFailure($this->t('Invalid session state. Please try again.'), null, $request);
        }

        if ($error = $request->request->get('error')) {
            $this->logger->notice('Apple Auth cancelled or returned error: @error', ['@error' => $error]);
            return $this->onAppleAuthFailure($this->t('Apple authentication was cancelled.'), $storedState, $request);
        }

        $idToken = $request->request->get('id_token');
        if (empty($idToken)) {
            $this->logger->error('Apple identity token missing in callback request.');
            return $this->onAppleAuthFailure($this->t('Identity token missing.'), $storedState, $request);
        }

        try {
            $payload = $this->verifyToken($idToken);

            if ($payload && !empty($payload['email'])) {
                if (isset($payload['email_verified']) && $payload['email_verified'] !== true && $payload['email_verified'] !== 'true') {
                    return $this->onAppleAuthFailure($this->t('Your Apple email address is not verified.'), $storedState, $request);
                }

                $userParam = $request->request->get('user');
                if (!empty($userParam) && is_string($userParam)) {
                    $userData = json_decode($userParam, true);
                    if (!empty($userData['name'])) {
                        if (!empty($userData['name']['firstName'])) {
                            $payload['given_name'] = $userData['name']['firstName'];
                        }
                        if (!empty($userData['name']['lastName'])) {
                            $payload['family_name'] = $userData['name']['lastName'];
                        }
                    }
                }

                return $this->onAppleAuthSuccess(strtolower($payload['email']), $payload, $storedState, $request);
            }
        } catch (Exception|GuzzleException $e) {
            $this->logger->error('Exception during Apple verification: @msg', ['@msg' => $e->getMessage()]);
        }

        return $this->onAppleAuthFailure($this->t('An error occurred during Apple verification.'), $storedState, $request);
    }
}
