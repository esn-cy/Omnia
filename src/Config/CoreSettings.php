<?php

namespace Drupal\esn_cyprus_core\Config;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

class CoreSettings
{
    public const CONFIG_NAME = 'esn_cyprus_core.settings';

    private Config|ImmutableConfig $config;

    public function __construct(ConfigFactoryInterface $config_factory, bool $editable = false) {
        if ($editable) {
            $this->config = $config_factory->getEditable(self::CONFIG_NAME);
        } else {
            $this->config = $config_factory->get(self::CONFIG_NAME);
        }
    }

    public function getStripeSwitch(): ?bool
    {
        return $this->config->get('switch_stripe') ?? false;
    }

    public function setStripeSwitch(bool $value): self
    {
        $this->config->set('switch_stripe', $value);
        return $this;
    }

    public function getGoogleSwitch(): ?bool {
        return $this->config->get('switch_google') ?? false;
    }

    public function setGoogleSwitch(bool $value): self {
        $this->config->set('switch_google', $value);
        return $this;
    }

    public function getAppleSwitch(): ?bool {
        return $this->config->get('switch_apple') ?? false;
    }

    public function setAppleSwitch(bool $value): self {
        $this->config->set('switch_apple', $value);
        return $this;
    }

    public function getStripeSecretKey(): ?string {
        return $this->config->get('stripe_secret_key') ?? null;
    }

    public function setStripeSecretKey(string $value): self {
        $this->config->set('stripe_secret_key', $value);
        return $this;
    }

    public function getGoogleClientEmail(): ?string {
        return $this->config->get('google_client_email') ?? null;
    }

    public function setGoogleClientEmail(string $value): self {
        $this->config->set('google_client_email', $value);
        return $this;
    }

    public function getGooglePrivateKey(): ?string {
        return $this->config->get('google_private_key') ?? null;
    }

    public function setGooglePrivateKey(string $value): self {
        $this->config->set('google_private_key', $value);
        return $this;
    }

    public function getGooglePrivateKeyID(): ?string {
        return $this->config->get('google_private_key_id') ?? null;
    }

    public function setGooglePrivateKeyID(string $value): self {
        $this->config->set('google_private_key_id', $value);
        return $this;
    }

    public function getGoogleProjectID(): ?string {
        return $this->config->get('google_project_id') ?? null;
    }

    public function setGoogleProjectID(string $value): self {
        $this->config->set('google_project_id', $value);
        return $this;
    }

    public function getGoogleClientID(): ?string {
        return $this->config->get('google_client_id') ?? null;
    }

    public function setGoogleClientID(string $value): self {
        $this->config->set('google_client_id', $value);
        return $this;
    }

    public function getGoogleIssuerID(): ?string {
        return $this->config->get('google_issuer_id') ?? null;
    }

    public function setGoogleIssuerID(string $value): self {
        $this->config->set('google_issuer_id', $value);
        return $this;
    }

    public function getAppleTeamID(): ?string
    {
        return $this->config->get('apple_team_id') ?? null;
    }

    public function setAppleTeamID(string $value): self
    {
        $this->config->set('apple_team_id', $value);
        return $this;
    }

    public function save(): void {
        $this->config->save();
    }
}