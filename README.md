<!--suppress HtmlDeprecatedAttribute -->
<h1 align="center">
    <img src="./assets/images/core.svg" width="200" alt="ESN Cyprus Core Logo"/><br>
    ESN Cyprus Core
</h1>

ESN Cyprus Core is a foundational Drupal module that provides shared functionality, utility services, and third-party
integrations for ESN Cyprus operations.

This module does not provide standalone user-facing features. Instead, it acts as a centralized library that other ESN
Cyprus modules (like the [ESN Membership Manager](https://github.com/esn-cy/ESN-Membership-Manager)) can rely on for
consistent file handling, email dispatching, and digital wallet integrations.

## Features & Services

This module registers several core services that can be injected into your custom modules:

* **File Service (`esn_cyprus_core.file_service`)**: A robust wrapper around Drupal's file system for creating, moving,
  replacing, and reading managed files, as well as handling temporary files.
* **Google Wallet Service (`esn_cyprus_core.google_service`)**: Integration with the Google Wallet API using Service
  Account credentials. Handles creating, updating, and deleting Generic Classes and Objects, as well as uploading
  private images.
* **Apple Wallet Service (`esn_cyprus_core.apple_service`)**: Generates `.pkpass` files using the `pkpass/pkpass`
  library and handles sending APNs push notifications for pass updates.
* **Email Manager (`esn_cyprus_core.email_manager`)**: A utility for sending emails seamlessly using Drupal's Twig
  rendering system in isolation.
* **Enum-Backed Entities**: Provides base classes and interfaces (`EnumBackedEntityBase`, `EnumBackedEntityStorage`) to
  create Drupal content entities with strongly-typed, enum-backed fields.

## Requirements

This module requires **Drupal 10 or 11** and relies on several external PHP libraries managed via Composer:

* `google/auth` (^1.50)
* `google/apiclient` (^2.19)
* `google/apiclient-services` (^0.441)
* `pkpass/pkpass` (^2.5.1)
* PHP Extensions: `ext-openssl`, `ext-fileinfo`

## Installation

1. Require the module via Composer to automatically fetch the external dependencies:
   ```bash
   composer require esn-cy/core
   ```
2. Enable the module via Drush:
   ```bash
   drush en esn_cyprus_core
   ```

## Configuration

To access the settings form, a user must have the `manage core settings` permission.

Navigate to ESN Cyprus Core > Core Settings (/admin/esn-cyprus-core/settings).

From the settings page, you can configure:

* **Integrations Toggle**: Globally enable or disable Apple and Google integrations.
* **Email Settings**: Define the default sender address, sender name, and the HTML footer for outgoing Twig emails.
* **Google Wallet**: Upload your Google Service Account `.json` key file and define your Google Wallet Issuer ID. The
  module will securely parse the JSON file and extract the keys.
* **Apple Wallet**: Set your Apple Team ID.