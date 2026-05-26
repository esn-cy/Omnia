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
     * @throws Exception
     */
    public function createPass(array $passData, array $images, string $certificateP12, string $certificatePassword): ?string
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

    public function sendUpdateNotification(string $pushToken, string $passTypeID, string $certificatePEM, string $certificatePassword): bool
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