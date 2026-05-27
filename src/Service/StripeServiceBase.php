<?php /** @noinspection PhpUnused */

namespace Drupal\esn_cyprus_core\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\esn_cyprus_core\Config\CoreSettings;
use Exception;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentLink;
use Stripe\Price;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;

class StripeServiceBase
{
    protected ConfigFactoryInterface $configFactory;
    protected LoggerChannelInterface $logger;
    protected ?StripeClient $client = NULL;

    public function __construct(
        ConfigFactoryInterface        $configFactory,
        LoggerChannelFactoryInterface $loggerFactory
    )
    {
        $this->configFactory = $configFactory;
        $this->logger = $loggerFactory->get('esn_membership_manager');
    }

    /**
     * Gets the initialized Stripe client.
     *
     * @return ?StripeClient The Stripe client instance, or null if the configuration is missing.
     */
    private function getClient(): ?StripeClient
    {
        if ($this->client) {
            return $this->client;
        }

        $moduleConfig = new CoreSettings($this->configFactory);

        if ($stripeSecretKey = $moduleConfig->getStripeSecretKey()) {
            $client = new StripeClient($stripeSecretKey);
            $this->client = $client;
            return $client;
        } else {
            $this->logger->error('Stripe Secret Key not set in the module configuration.');
            return NULL;
        }
    }

    /**
     * Create a Stripe payment link for the given application.
     *
     * @param array[] $prices An array containing the price IDs and quanitites to be included in the payment link.
     * @param ?array[] $metadata Optional: An array containing key pairs to be included in the payment link as metadata.
     *
     * @return ?PaymentLink The payment link object that was created, null if creation failed.
     *
     * @throws Exception It's thrown when the ESN Cyprus Core Stripe configuration is invalid.
     */
    protected function createPaymentLink(array $prices, ?array $metadata = null): ?PaymentLink
    {
        if (!$this->getClient()) {
            throw new Exception('Stripe Secret Key not set in the module configuration.');
        }

        $linkParameters = ['line_items' => $prices];
        if (!empty($metadata)) {
            $linkParameters['metadata'] = $metadata;
        }

        try {
            return $this->client->paymentLinks->create($linkParameters);
        } catch (ApiErrorException $e) {
            $this->logger->error('Payment Link creation error. @error', ['@error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Disables a Stripe payment link.
     *
     * @param string $linkID The ID of the link to be disabled.
     *
     * @return bool A boolean value indicating if the link disabling was successful.
     *
     * @throws Exception It's thrown when the ESN Cyprus Core Stripe configuration is invalid.
     */
    public function disablePaymentLink(string $linkID): bool
    {
        if (!$this->getClient()) {
            throw new Exception('Stripe Secret Key not set in the module configuration.');
        }

        try {
            $this->client->paymentLinks->update(
                $linkID,
                ['active' => false]
            );
            return true;
        } catch (ApiErrorException $e) {
            $this->logger->error('Payment Link disabling error. @error', ['@error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Creates a webhook event out of a request.
     *
     * @param Request $request The request to be processed.
     * @param string $webhookSecret The webhook secret to be used to verify the request.
     *
     * @return ?Event The event object that was constructed, null if the event couldn't be cosntructed.
     */
    protected function createWebhookEvent(Request $request, string $webhookSecret): ?Event
    {
        $payload = $request->getContent();
        $signatureHeader = $request->headers->get('Stripe-Signature');

        try {
            return Webhook::constructEvent($payload, $signatureHeader, $webhookSecret);
        } catch (Exception $e) {
            $this->logger->error('Unable to construct webhook event. @error', ['@error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Gets price object from a given price ID.
     *
     * @param string $priceID The price ID to be retrieved.
     *
     * @return ?Price The price object that was retrieved, null if there was a problem retrieving it.
     *
     * @throws Exception It's thrown when the ESN Cyprus Core Stripe configuration is invalid.
     */
    protected function getPrice(string $priceID): ?Price
    {
        if (!$this->getClient()) {
            throw new Exception('Stripe Secret Key not set in the module configuration.');
        }

        try {
            return $this->client->prices->retrieve($priceID);
        } catch (Exception $e) {
            $this->logger->error('Unable to get price. @error', ['@error' => $e->getMessage()]);
            return null;
        }
    }
}