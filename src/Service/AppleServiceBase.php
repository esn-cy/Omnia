<?php /** @noinspection PhpUnused */

namespace Drupal\esn_cyprus_core\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use PKPass\PKPass;
use PKPass\PKPassException;

class AppleServiceBase
{
    protected FileServiceBase $fileService;
    protected ClientInterface $httpClient;
    protected LoggerChannelInterface $logger;

    public function __construct(
        FileServiceBase               $fileService,
        ClientInterface               $httpClient,
        LoggerChannelFactoryInterface $loggerFactory
    )
    {
        $this->fileService = $fileService;
        $this->httpClient = $httpClient;
        $this->logger = $loggerFactory->get('esn_cyprus_core');
    }

    /**
     * Creates an Apple Wallet pass.
     *
     * @param array $passData An array containing the pass data.
     * @param array $images An array containing the paths to the images for the pass and their names.
     * @param string $certificateP12 The P12 certificate string.
     * @param string $certificatePassword The password for the P12 certificate.
     *
     * @return string|null The created pass data as a string, or null if creation failed.
     *
     * @throws Exception
     */
    protected function createPass(array $passData, array $images, string $certificateP12, string $certificatePassword): ?string
    {
        $pass = new PKPass();

        $pass->setCertificateString($certificateP12);
        $pass->setCertificatePassword($certificatePassword);

        $pass->setData($passData);

        foreach ($images as $name => $path) {
            $pass->addFile($path, $name);
        }

        try {
            return $pass->create();
        } catch (PKPassException $e) {
            $this->logger->error('Apple Wallet Pass creation failed: ' . $e->getMessage());
            return NULL;
        }
    }

    /**
     * Sends an update notification via Apple Push Notification service (APNs).
     *
     * @param string $pushToken The device push token.
     * @param string $passTypeID The pass type ID.
     * @param string $certificatePEM The PEM certificate string.
     * @param string $certificatePassword The password for the PEM certificate.
     *
     * @return bool True if the notification was sent successfully, false otherwise.
     */
    protected function sendUpdateNotification(string $pushToken, string $passTypeID, string $certificatePEM, string $certificatePassword): bool
    {
        $certificatePath = $this->fileService->getTemporaryFile('apns_cert_', '.pem');

        try {
            file_put_contents($certificatePath, $certificatePEM);

            $this->httpClient->request('POST', "https://api.push.apple.com/3/device/$pushToken", [
                'version' => '2.0',
                'body' => '{}',
                'cert' => [$certificatePath, $certificatePassword],
                'headers' => [
                    'apns-topic' => $passTypeID,
                ],
            ]);

            return true;
        } catch (GuzzleException $e) {
            $this->logger->error('APNs Push Failed for token @token: @error', ['@token' => $pushToken, '@error' => $e->getMessage(),]);

            if (method_exists($e, 'hasResponse') && $e->hasResponse()) {
                /** @var RequestException $e */
                $responseBody = (string)$e->getResponse()->getBody();
                $statusCode = $e->getResponse()->getStatusCode();
                $this->logger->error('APNs Error Details (@code): @body', ['@code' => $statusCode, '@body' => $responseBody]);
            }

            return false;
        } finally {
            if (file_exists($certificatePath)) {
                unlink($certificatePath);
            }
        }
    }
}