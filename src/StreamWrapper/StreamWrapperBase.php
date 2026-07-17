<?php

namespace Drupal\omnia\StreamWrapper;

use Drupal;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a custom stream wrapper for Omnia modules.
 */
abstract class StreamWrapperBase extends LocalStream
{
    abstract function moduleMachineName(): string;
    abstract function moduleFormatedName(): string;
    abstract function isPrivate(): bool;

    static protected array $secureCheckedDirs = [];

    /**
     * {@inheritdoc}
     */
    public function getName(): string|TranslatableMarkup
    {
        $type = $this->isPrivate() ? 'Private' : 'Public';
        return t($this->moduleFormatedName() . " $type Files");
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string|TranslatableMarkup
    {
        $type = $this->isPrivate() ? 'private' : 'public';
        return t("Dedicated $type storage for " . $this->moduleFormatedName() .' files.');
    }

    /**
     * Ensures that the private directory is protected by an .htaccess file.
     */
    private function ensureHtaccess(): void
    {
        $directory = $this->getDirectoryPath();

        if (is_dir($directory)) {
            $htaccessPath = $directory . '/.htaccess';
            if (!file_exists($htaccessPath)) {
                $content = "Order deny,allow\nDeny from all\n";
                $content .= "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n";
                file_put_contents($htaccessPath, $content);
            }
            self::$secureCheckedDirs[$directory] = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir($uri, $mode, $options): bool
    {
        $result = parent::mkdir($uri, $mode, $options);
        if ($this->isPrivate() && $result) {
            $this->ensureHtaccess();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_open($uri, $mode, $options, &$opened_path): bool
    {
        if ($this->isPrivate() && empty(self::$secureCheckedDirs[$this->getDirectoryPath()])) {
            $this->ensureHtaccess();
        }
        return parent::stream_open($uri, $mode, $options, $opened_path);
    }

    /**
     * {@inheritdoc}
     */
    public function getDirectoryPath(): string
    {
        if ($this->isPrivate()) {
            return DRUPAL_ROOT . '/../../private/' . $this->moduleMachineName() . '_storage';
        }

        $sitePath = Drupal::getContainer()->getParameter('site.path');
        return DRUPAL_ROOT . '/' . $sitePath . '/files/' . $this->moduleMachineName() . '_storage';
    }

    abstract public function getExternalUrl(): GeneratedUrl|string;
}