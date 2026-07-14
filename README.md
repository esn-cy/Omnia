<!--suppress HtmlDeprecatedAttribute -->
<h1 align="center">
    <img src="./assets/images/logo.svg" width="200" alt="Omnia Logo"/><br>
    Omnia
</h1>

Omnia is an umbrella of services designed to help ESN digitalize its operations. It provides shared functionality,
utility services, and third-party integrations as a foundational Drupal module.

While it does not provide standalone user-facing features by itself, Omnia acts as a centralized ecosystem that other
ESN Cyprus modules (like the [ESN Membership Manager](https://github.com/esn-cy/ESN-Membership-Manager)) can rely on for
consistent file handling, email dispatching, and digital wallet integrations.

## Features & Services

This module registers several core services that can be injected into your custom modules:

* **File Service (`omnia.file_service`)**: A robust wrapper around Drupal's file system for creating, moving,
  replacing, and reading managed files, as well as handling temporary files.
* **Google Wallet Service (`omnia.google_service`)**: Integration with the Google Wallet API using Service
  Account credentials. Handles creating, updating, and deleting Generic Classes and Objects, as well as uploading
  private images.
* **Apple Wallet Service (`omnia.apple_service`)**: Generates `.pkpass` files using the `pkpass/pkpass`
  library and handles sending APNs push notifications for pass updates.
* **Email Manager (`omnia.email_manager`)**: A utility for sending emails seamlessly using Drupal's Twig
  rendering system in isolation.
* **Stripe Service (`omnia.stripe_service`)**: Integration with the Stripe API for creating payment links,
  fetching prices, and validating webhooks.
* **Enum-Backed Entities**: Provides base classes and interfaces (`EnumBackedEntityBase`, `EnumBackedEntityStorage`) to
  create Drupal content entities with strongly-typed, enum-backed fields.

## Requirements

This module requires **Drupal 10 or 11** and relies on several external PHP libraries managed via Composer:

* `esn/esn_accounts_api` (^1.1)
* `stripe/stripe-php` (^20.1)
* `google/auth` (^1.50)
* `google/apiclient` (^2.19)
* `google/apiclient-services` (^0.441)
* `pkpass/pkpass` (^2.5.1)
* PHP Extensions: `ext-openssl`, `ext-fileinfo`

## Installation

1. Require the module via Composer to automatically fetch the external dependencies:
   ```bash
   composer require esn-cy/omnia
   ```
2. Enable the module via Drush:
   ```bash
   drush en omnia
   ```

## Configuration

To access the settings form, a user must have the `manage omnia settings` permission.

Navigate to Omnia > Omnia Settings (/admin/omnia/settings).

From the settings page, you can configure:

* **Integrations Toggle**: Globally enable or disable Apple and Google integrations.
* **Email Settings**: Define the default sender address, sender name, and the HTML footer for outgoing Twig emails.
* **Stripe**: Set your Stripe Secret Key to enable Stripe API integrations.
* **Google Wallet**: Upload your Google Service Account `.json` key file and define your Google Wallet Issuer ID. The
  module will securely parse the JSON file and extract the keys.
* **Apple Wallet**: Set your Apple Team ID.