<?php /** @noinspection PhpUnused */

namespace Drupal\esn_cyprus_core\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Exception;

class EmailManagerBase
{
    protected MailManagerInterface $mailManager;
    protected RendererInterface $renderer;
    protected LoggerChannelInterface $logger;

    public function __construct(
        MailManagerInterface          $mailManager,
        RendererInterface             $renderer,
        LoggerChannelFactoryInterface $loggerFactory,
    )
    {
        $this->mailManager = $mailManager;
        $this->renderer = $renderer;
        $this->logger = $loggerFactory->get('esn_cyprus_core');
    }

    /**
     * Send an email using a Twig template.
     */
    public function sendEmail(string $to, string $key, array $data, array $params, string $moduleName): void
    {
        $renderArray = [];
        foreach ($data as $name => $value) {
            $renderArray['#' . $name] = $value ?? null;
        }

        try {
            if (method_exists($this->renderer, 'renderInIsolation')) {
                // Drupal 10.3+
                $htmlBody = $this->renderer->renderInIsolation($renderArray);
            } else {
                // Drupal 9 / <10.3
                /** @noinspection PhpDeprecationInspection */
                $htmlBody = $this->renderer->renderPlain($renderArray);
            }
        } catch (Exception $e) {
            $this->logger->error('Email Send Error: @message', ['@message' => $e->getMessage()]);
            return;
        }

        $params = ['body' => $htmlBody] + $params;

        $this->mailManager->mail($moduleName, $key, $to, 'en', $params);
        $this->logger->info('Email Send Successfully to @email', ['@email' => $to]);
    }
}