<?php

declare(strict_types=1);

namespace Drupal\mvault_webform\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mvault\ValueObject\Membership;
use Drupal\mvault\Exception\MvaultException;
use Drupal\webform\Element\WebformName;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\mvault\Client\MvaultClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MVault membership webform handler.
 *
 * Creates or renews a PBS MVault membership when a webform is submitted.
 * Checks for an existing active membership before taking action.
 *
 * @WebformHandler(
 *   id = "mvault_membership",
 *   label = @Translation("MVault Membership"),
 *   category = @Translation("PBS"),
 *   description = @Translation("Creates or renews a PBS MVault membership upon
 *   form submission."), cardinality =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class MvaultWebformHandler extends WebformHandlerBase {

  /**
   * The MVault API client.
   *
   * NOTE: Not injected via constructor — WebformHandlerBase serializes handler
   * instances. Services that connect to the database must be assigned as
   * properties in create() to avoid serialization exceptions.
   *
   * @var \Drupal\mvault\Client\MvaultClientInterface
   */
  protected MvaultClientInterface $mvaultClient;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mvaultClient = $container->get('mvault.client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_mappings' => [
        'first_name_field' => '',
        'last_name_field' => '',
        'email_field' => '',
        'library_id_field' => '',
      ],
      'membership_id_field' => '',
      'membership_id_pattern' => 'en_{field}',
      'membership_duration_days' => 0,
      'success_message' => 'Your PBS Passport membership has been activated. Thank you!',
      'already_active_message' => 'You already have an active PBS Passport membership and are not eligible for this offer.',
      'error_message' => 'We were unable to process your membership at this time. Please contact support.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $webform = $this->getWebform();
    $element_options = $this->buildElementOptions($webform->getElementsDecodedAndFlattened());

    $form['field_mappings'] = [
      '#type' => 'details',
      '#title' => $this->t('Field mappings'),
      '#open' => TRUE,
      '#weight' => 10,
    ];

    $form['field_mappings']['first_name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('First name field'),
      '#required' => TRUE,
      '#options' => $element_options,
      '#empty_option' => $this->t('- Select field -'),
      '#default_value' => $this->configuration['field_mappings']['first_name_field'],
    ];

    $form['field_mappings']['last_name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Last name field'),
      '#required' => TRUE,
      '#options' => $element_options,
      '#empty_option' => $this->t('- Select field -'),
      '#default_value' => $this->configuration['field_mappings']['last_name_field'],
    ];

    $form['field_mappings']['email_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Email field'),
      '#description' => $this->t('Used to look up existing memberships before creating a new one.'),
      '#options' => $element_options,
      '#empty_option' => $this->t('- Select field -'),
      '#default_value' => $this->configuration['field_mappings']['email_field'],
      '#required' => TRUE,
    ];

    $form['field_mappings']['library_id_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Library ID field'),
      '#description' => $this->t('Stored in additional_metadata. Leave empty if not applicable.'),
      '#options' => $element_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['field_mappings']['library_id_field'],
    ];

    $form['membership_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Membership settings'),
      '#open' => TRUE,
      '#weight' => 15,
    ];

    $form['membership_settings']['membership_id_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Membership ID field'),
      '#description' => $this->t('The webform field containing the supporter ID used to build the membership ID (e.g., an Engaging Networks ID).'),
      '#options' => $element_options,
      '#empty_option' => $this->t('- Select field -'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['membership_id_field'],
    ];

    $form['membership_settings']['membership_id_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Membership ID pattern'),
      '#description' => $this->t('Pattern for the membership ID. Use <code>{field}</code> as a placeholder for the value of the Membership ID field. Example: <code>en_{field}</code>'),
      '#default_value' => $this->configuration['membership_id_pattern'],
      '#required' => TRUE,
      '#placeholder' => 'en_{field}',
    ];

    $form['membership_settings']['membership_duration_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Membership duration (days)'),
      '#description' => $this->t('Number of days from yesterday for the membership expiration date. Enter 0 to use the module-level default (365 days).'),
      '#default_value' => $this->configuration['membership_duration_days'],
      '#min' => 0,
      '#max' => 3650,
    ];

    $form['messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Messages'),
      '#open' => FALSE,
      '#weight' => 25,
    ];

    $form['messages']['success_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Success message'),
      '#description' => $this->t('Displayed when a membership is successfully created or renewed.'),
      '#default_value' => $this->configuration['success_message'],
      '#rows' => 2,
    ];

    $form['messages']['already_active_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Already active message'),
      '#description' => $this->t('Displayed when the user already has an active membership and is ineligible.'),
      '#default_value' => $this->configuration['already_active_message'],
      '#rows' => 2,
    ];

    $form['messages']['error_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('Displayed when the API call fails. Keep generic — do not expose API details.'),
      '#default_value' => $this->configuration['error_message'],
      '#rows' => 2,
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['field_mappings'] = [
      'first_name_field' => $form_state->getValue([
        'field_mappings',
        'first_name_field',
      ]),
      'last_name_field' => $form_state->getValue([
        'field_mappings',
        'last_name_field',
      ]),
      'email_field' => $form_state->getValue(['field_mappings', 'email_field']),
      'library_id_field' => $form_state->getValue([
        'field_mappings',
        'library_id_field',
      ]),
    ];
    $this->configuration['membership_id_field'] = $form_state->getValue([
      'membership_id_field',
    ]);
    $this->configuration['membership_id_pattern'] = $form_state->getValue([
      'membership_id_pattern',
    ]);
    $this->configuration['membership_duration_days'] = (int) $form_state->getValue([
      'membership_duration_days',
    ]);
    $this->configuration['success_message'] = $form_state->getValue([
      'success_message',
    ]);
    $this->configuration['already_active_message'] = $form_state->getValue([
      'already_active_message',
    ]);
    $this->configuration['error_message'] = $form_state->getValue([
      'error_message',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): array {
    $defaults = $this->defaultConfiguration();
    $mappings = $this->configuration['field_mappings'] ?? $defaults['field_mappings'];
    $emailField = $mappings['email_field'] ?? '';
    $pattern = $this->configuration['membership_id_pattern'] ?? $defaults['membership_id_pattern'];
    $durationDays = $this->configuration['membership_duration_days'] ?? 0;

    return [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Email field: @field', ['@field' => $emailField !== '' ? $emailField : $this->t('(not set)')]),
        $this->t('Membership ID pattern: @pattern', ['@pattern' => $pattern]),
        $this->t('Duration: @days days', ['@days' => $durationDays !== 0 ? (string) $durationDays : $this->t('module default')]),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
    $mappings = $this->configuration['field_mappings'] ?? [];
    $emailField = $mappings['email_field'] ?? '';

    if (empty($emailField)) {
      $this->logSkip('email_field mapping is not configured. Handler skipped', $webform_submission->id());
      return;
    }

    $data = $webform_submission->getData();
    $extractedFields = $this->extractMappedFields($data, $mappings);

    if (empty($extractedFields['email'])) {
      $this->logSkip('email value is empty. Handler skipped', $webform_submission->id());
      return;
    }
    $membershipId = $this->formatMembershipId($data);

    try {
      $result = $this->processMembership(
        $extractedFields['email'],
        $membershipId,
        $extractedFields
      );
      $this->displaySuccessMessage($result);
    }
    catch (MvaultException $e) {
      $this->logger()->error(
        'MVault API error for membership @id (email: @email): @message',
        [
          '@id' => $membershipId,
          '@email' => $extractedFields['email'],
          '@message' => $e->getMessage(),
          'exception' => $e,
        ]
      );
      $this->displayConfigMessage('error_message', 'addError');
    }
  }

  /**
   * Formats the membership ID based on the configured pattern and field.
   *
   * @param array $data
   *   The webform submission data.
   *
   * @return array|string|string[]
   *   The formatted membership ID.
   */
  private function formatMembershipId(array $data): string|array {
    $membershipIdSource = $this->extractFieldValue(
      $data, $this->configuration['membership_id_field'] ?? ''
    );
    return str_replace(
      '{field}',
      $membershipIdSource,
      $this->configuration['membership_id_pattern']
    );
  }

  /**
   * Orchestrates the create-or-renew decision for a membership.
   *
   * @param string $email
   *   The member's email address.
   * @param string $membershipId
   *   The formatted membership identifier.
   * @param array<string, string> $extractedFields
   *   The mapped field values from the submission.
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   The membership returned by the API after create or renew.
   *
   * @throws \DateMalformedStringException
   * @throws \Drupal\mvault\Exception\MvaultApiException
   * @throws \Drupal\mvault\Exception\MvaultNotFoundException
   * @throws \JsonException
   */
  private function processMembership(
    string $email,
    string $membershipId,
    array $extractedFields
  ): Membership {
    $activeMembership = $this->mvaultClient->getActiveMembershipByEmail($email);

    if ($activeMembership !== NULL) {
      $this->handleActiveMembership($email, $membershipId);
      return $activeMembership;
    }

    $existingMembership = $this->mvaultClient->getMembershipByEmail($email);
    $membership = $this->createMembership($extractedFields);

    return $existingMembership !== NULL
      ? $this->mvaultClient->renewMembership($membershipId, $membership->expireDate, $existingMembership)
      : $this->mvaultClient->createMembership($membershipId, $membership);
  }

  /**
   * Handles the case where the user already has an active membership.
   *
   * @param string $email
   *   The member's email address.
   * @param string $membershipId
   *   The membership ID being processed.
   */
  private function handleActiveMembership(string $email, string $membershipId): void {
    $this->logger()->info(
      'MVault: active membership found for @email (id: @id), skipping creation.',
      ['@email' => $email, '@id' => $membershipId],
    );

    $this->displayConfigMessage('already_active_message', 'addWarning');
  }

  /**
   * Displays the appropriate success message after creating or renewing a
   * membership.
   *
   * @param \Drupal\mvault\ValueObject\Membership $membership
   *   The membership returned by the API.
   */
  private function displaySuccessMessage(Membership $membership): void {
    if ($membership->token !== NULL && $membership->token !== '') {
      $activationUrl = 'https://www.pbs.org/passport/activate/?token=' . $membership->token;
      $this->messenger()->addStatus($this->t(
        'Your PBS Passport membership has been activated. <a href="@url">Click here to activate your account</a>.',
        ['@url' => $activationUrl],
      ));
      return;
    }

    $this->displayConfigMessage('success_message', 'addStatus');
  }

  /**
   * Extracts all mapped field values from submission data in a single pass.
   *
   * @param array<string, mixed> $data
   *   The webform submission data.
   * @param array<string, string> $mappings
   *   The configured field mappings.
   *
   * @return array<string, string>
   *   Keyed array with: email, firstName, lastName, libraryId.
   */
  private function extractMappedFields(array $data, array $mappings): array {
    return [
      'email' => $this->extractFieldValue($data, $mappings['email_field'] ?? ''),
      'firstName' => $this->extractFieldValue($data, $mappings['first_name_field'] ?? ''),
      'lastName' => $this->extractFieldValue($data, $mappings['last_name_field'] ?? ''),
      'libraryId' => $this->extractFieldValue($data, $mappings['library_id_field'] ?? ''),
    ];
  }

  /**
   * Builds a Membership value object from already-extracted field values.
   *
   * @param array<string, string> $extractedFields
   *   The mapped field values from the submission.
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   The membership value object populated from submission data.
   * @throws \DateMalformedStringException
   */
  private function createMembership(array $extractedFields): Membership {
    $startDate = new \DateTimeImmutable('yesterday');
    $durationDays = $this->resolveDurationDays();
    $expireDate = $startDate->modify(sprintf('+%d days', $durationDays));
    $offerId = (string) $this->configFactory->get('mvault.settings')->get('default_offer_id');

    $additionalMetadata = $extractedFields['libraryId'] !== ''
      ? ['library_id' => $extractedFields['libraryId']]
      : NULL;

    return new Membership(
      firstName: $extractedFields['firstName'],
      lastName: $extractedFields['lastName'],
      email: $extractedFields['email'],
      offer: $offerId,
      startDate: $startDate,
      expireDate: $expireDate,
      additionalMetadata: $additionalMetadata,
    );
  }

  /**
   * Resolves the membership duration in days.
   *
   * Falls back to 365 days if the handler configuration is set to 0 (use
   * module default).
   *
   * @return int
   *   The number of days for the membership duration.
   */
  private function resolveDurationDays(): int {
    $handlerDays = (int) $this->configuration['membership_duration_days'];

    if ($handlerDays > 0) {
      return $handlerDays;
    }

    $moduleDays = (int) ($this->configFactory->get('mvault.settings')
      ->get('membership_duration_days') ?? 0);

    return $moduleDays > 0 ? $moduleDays : 365;
  }

  /**
   * Extracts a string value from submission data for a given field key.
   *
   * @param array<string, mixed> $data
   *   The webform submission data.
   * @param string $fieldKey
   *   The field key to extract.
   *
   * @return string
   *   The extracted value, or an empty string if the field is not set.
   */
  private function extractFieldValue(array $data, string $fieldKey): string {
    if ($fieldKey === '') {
      return '';
    }

    // Support composite element sub-keys encoded as "parent__sub" (e.g.
    // "name__first" for the first-name sub-element of a webform_name field).
    if (str_contains($fieldKey, '__')) {
      [$parentKey, $subKey] = explode('__', $fieldKey, 2);
      return (string) ($data[$parentKey][$subKey] ?? '');
    }

    return (string) ($data[$fieldKey] ?? '');
  }

  /**
   * Returns the logger channel for this handler.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger channel.
   */
  private function logger(): LoggerChannelInterface {
    return $this->loggerFactory->get('mvault_webform');
  }

  /**
   * Displays a messenger message using a configured message string.
   *
   * @param string $configKey
   *   The configuration key holding the message text.
   * @param string $method
   *   The messenger method to call (e.g. 'addStatus', 'addWarning',
   *   'addError').
   */
  private function displayConfigMessage(string $configKey, string $method): void {
    $this->messenger()->{$method}($this->t('@message', [
      '@message' => $this->configuration[$configKey],
    ]));
  }

  /**
   * Logs a skip warning for a submission that cannot be processed.
   *
   * @param string $reason
   *   A human-readable explanation of why the handler was skipped.
   * @param string|int $sid
   *   The webform submission ID.
   */
  private function logSkip(string $reason, string|int $sid): void {
    $this->logger()->warning(
      'MVault handler: @reason for submission @sid.',
      ['@reason' => $reason, '@sid' => $sid]
    );
  }

  /**
   * Builds a select options array from flattened webform elements.
   *
   * @param array<string, mixed> $elements
   *   The flattened webform elements array.
   *
   * @return array<string, string>
   *   An options array keyed by element key with labels as values.
   */
  private function buildElementOptions(array $elements): array {
    $options = [];

    foreach ($elements as $key => $element) {
      if (str_starts_with($key, '#')) {
        continue;
      }

      // Expand webform_name composites into individual sub-element options so
      // the handler can map first name and last name fields independently.
      if (isset($element['#type']) && $element['#type'] === 'webform_name') {
        $parentLabel = isset($element['#title']) ? (string) $element['#title'] : $key;
        foreach (WebformName::getCompositeElements([]) as $subKey => $subElement) {
          $subLabel = isset($subElement['#title']) ? (string) $subElement['#title'] : $subKey;
          $compositeKey = $key . '__' . $subKey;
          $options[$compositeKey] = $parentLabel . ': ' . $subLabel . ' (' . $compositeKey . ')';
        }
        continue;
      }

      $label = isset($element['#title']) ? (string) $element['#title'] : $key;
      $options[$key] = $label . ' (' . $key . ')';
    }

    return $options;
  }

}
