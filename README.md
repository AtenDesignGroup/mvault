# MVault

Provides PBS MVault API integration for Drupal, enabling PBS Passport
membership management. This module allows PBS member stations to create,
retrieve, and renew memberships through the MVault API.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/mvault).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/mvault).

## Table of contents

- Requirements
- Recommended modules
- Installation
- Configuration
- Submodules
- Troubleshooting
- Maintainers

## Requirements

This module requires no modules outside of Drupal core.

## Recommended modules

- [Key](https://www.drupal.org/project/key): Securely store your MVault API
  credentials. When installed, the module provides a key selector instead of
  a plain text field for the API key configuration.
- [Webform](https://www.drupal.org/project/webform): Required for the MVault
  Webform submodule, which provides a webform handler for membership
  management.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

1. Navigate to Administration > Configuration > Web services > MVault Settings
   (`/admin/config/services/mvault`).
2. Enter your **Station ID** (e.g., WGBH).
3. Configure your **API Key**:
  - If the Key module is installed: Select a Key entity containing credentials
    in `api_key:api_secret` format.
  - Without the Key module: Enter the credentials directly in the text field.
4. Optionally set a **Default Offer ID** for new memberships.
5. Set the **Membership Duration** in days (defaults to 365).
6. Save the configuration.

The module requires the "Administer site configuration" permission to access
the settings form.

## Submodules

### MVault Webform

Provides a Webform handler that creates or renews PBS MVault memberships upon
form submission.

**Requirements:**

- MVault (this module)
- Webform

**Usage:**

1. Enable the MVault Webform submodule.
2. Edit your webform and add a new handler.
3. Select "MVault Membership" from the handler list.
4. Configure the handler:
  - **Field mappings**: Map webform fields to membership data (email, first
    name, last name, membership ID, library ID, offer ID).
  - **Membership ID pattern**: Define how to generate membership IDs using
    the `{field}` placeholder (e.g., `en_{field}`).
  - **Membership duration**: Override the module default or use 0 for the
    module-level setting.
  - **Messages**: Customize success, already-active, and error messages.

The handler will:

- Check for an existing active membership before creating a new one.
- Renew expired memberships when found.
- Create new memberships when none exist.
- Display an activation link when a token is returned by the API.

## Troubleshooting

**API connection issues:**

- Verify your Station ID and API credentials are correct.
- Check the Drupal logs at Reports > Recent log messages for detailed error
  information.
- Ensure your server can make outbound HTTPS connections to the PBS API.

**Webform handler not processing:**

- Confirm the email field is mapped correctly in the handler configuration.
- Check that required fields (email) have values in the submission.
- Review the `mvault_webform` log channel for error details.

