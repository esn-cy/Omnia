<?php /** @noinspection PhpUnused */

namespace Drupal\omnia\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Exception;
use Google\Client as GoogleClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class GoogleAuthControllerBase extends ControllerBase
{
    protected LoggerChannelInterface $logger;

    public function __construct(
        LoggerChannelFactoryInterface $loggerFactory
    )
    {
        $this->logger = $loggerFactory->get('omnia');
    }

    public static function create(ContainerInterface $container): self
    {
        /** @var LoggerChannelFactoryInterface $loggerFactory */
        $loggerFactory = $container->get('logger.factory');

        return new static(
            $loggerFactory
        );
    }

    abstract protected function getClientID(): string;

    abstract protected function getClientSecret(): string;

    protected function getRedirectURI(Request $request): string
    {
        return $request->getUriForPath($request->getPathInfo());
    }

    abstract protected function onGoogleAuthSuccess(
        TranslatableMarkup|string $email,
        array $payload,
        ?array $stateData,
        Request $request
    ): RedirectResponse;

    abstract protected function onGoogleAuthFailure(
        TranslatableMarkup|string $errorMessage,
        ?array $stateData,
        Request $request
    ): RedirectResponse;

    public function callback(Request $request): RedirectResponse
    {
        $session = $request->getSession();
        $storedState = $session->get('google_oauth_state');
        $session->remove('google_oauth_state');

        $queryState = $request->query->get('state');
        if (empty($queryState) || empty($storedState['token']) || !hash_equals($storedState['token'], $queryState)) {
            $this->logger->warning('Google OAuth state mismatch / potential CSRF.');
            return $this->onGoogleAuthFailure($this->t('Invalid session state. Please try again.'), $storedState, $request);
        }

        if ($error = $request->query->get('error')) {
            $this->logger->notice('Google Auth cancelled or returned error: @error', ['@error' => $error]);
            return $this->onGoogleAuthFailure($this->t('Google authentication was cancelled.'), $storedState, $request);
        }

        $code = $request->query->get('code');
        if (empty($code)) {
            return $this->onGoogleAuthFailure($this->t('Authorization code missing.'), $storedState, $request);
        }

        try {
            $client = new GoogleClient();
            $client->setClientId($this->getClientID());
            $client->setClientSecret($this->getClientSecret());
            $client->setRedirectUri($this->getRedirectURI($request));

            $tokenPayload = $client->fetchAccessTokenWithAuthCode($code);

            if (!empty($tokenPayload['error'])) {
                $this->logger->error('Google token exchange error: @err', ['@err' => $tokenPayload['error_description'] ?? $tokenPayload['error']]);
                return $this->onGoogleAuthFailure($this->t('Failed to authenticate with Google.'), $storedState, $request);
            }

            if (isset($tokenPayload['id_token'])) {
                $payload = $client->verifyIdToken($tokenPayload['id_token']);

                if ($payload && !empty($payload['email'])) {
                    if (isset($payload['email_verified']) && !$payload['email_verified']) {
                        return $this->onGoogleAuthFailure($this->t('Your Google email address is not verified.'), $storedState, $request);
                    }
                    return $this->onGoogleAuthSuccess(strtolower($payload['email']), $payload, $storedState, $request);
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Exception during Google verification: @msg', ['@msg' => $e->getMessage()]);
        }

        return $this->onGoogleAuthFailure($this->t('An error occurred during Google verification.'), $storedState, $request);
    }
}