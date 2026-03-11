<?php

declare(strict_types=1);

namespace Drupal\mvault_webform\Plugin\WebformHandler;

use Drupal\mvault\ValueObject\Membership;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mvault\Exception\MvaultException;
use Drupal\webform\Plugin\WebformHandlerBase;
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
        'membership_id_field' => '',
        'first_name_field' => '',
        'last_name_field' => '',
        'email_field' => '',
        'library_id_field' => '',
        'offer_id_field' => '',
      ],
      'membership_id_pattern' => 'en_[webform_submission:values:{field}]',
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
    ];

    $form['field_mappings']['membership_id_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Membership ID field'),
      '#description' => $this->t('The webform field containing the supporter ID used to build the membership ID (e.g., an Engaging Networks ID).'),
      '#options' => $element_options,
      '#empty_option' => $this->t('- Select field -'),
      '#default_value' => $this->configuration['field_mappings']['membership_id_field'],
    ];

    $form['field_mappings']['first_name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('First name field'),
      '#options' => $element_options,
      '#empty_option' => $this->t('- Select field -'),
      '#default_value' => $this->configuration['field_mappings']['first_name_field'],
    ];

    $form['field_mappings']['last_name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Last name field'),
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

    $form['field_mappings']['offer_id_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Offer ID field'),
      '#description' => $this->t('The webform field containing the offer/tier identifier. Leave empty to use the module-level default offer.'),
      '#options' => $element_options,
      '#empty_option' => $this->t('- Use module default -'),
      '#default_value' => $this->configuration['field_mappings']['offer_id_field'],
    ];

    $form['membership_id_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Membership ID pattern'),
      '#description' => $this->t('Pattern for the membership ID. Use <code>{field}</code> as a placeholder for the value of the Membership ID field. Example: <code>en_{field}</code>'),
      '#default_value' => $this->configuration['membership_id_pattern'],
      '#required' => TRUE,
      '#placeholder' => 'en_{field}',
    ];

    $form['membership_duration_days'] = [
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
      'membership_id_field' => $form_state->getValue('membership_id_field'),
      'first_name_field' => $form_state->getValue('first_name_field'),
      'last_name_field' => $form_state->getValue('last_name_field'),
      'email_field' => $form_state->getValue('email_field'),
      'library_id_field' => $form_state->getValue('library_id_field'),
      'offer_id_field' => $form_state->getValue('offer_id_field'),
    ];

    $this->configuration['membership_id_pattern'] = $form_state->getValue('membership_id_pattern');
    $this->configuration['membership_duration_days'] = (int) $form_state->getValue('membership_duration_days');
    $this->configuration['success_message'] = $form_state->getValue('success_message');
    $this->configuration['already_active_message'] = $form_state->getValue('already_active_message');
    $this->configuration['error_message'] = $form_state->getValue('error_message');
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): array {
    $mappings = $this->configuration['field_mappings'];

    return [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Email field: @field', ['@field' => $mappings['email_field'] ?: $this->t('(not set)')]),
        $this->t('Membership ID pattern: @pattern', ['@pattern' => $this->configuration['membership_id_pattern']]),
        $this->t('Duration: @days days', ['@days' => $this->configuration['membership_duration_days'] ?: $this->t('module default')]),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
    $data = $webform_submission->getData();
    $mappings = $this->configuration['field_mappings'];

    $email = $this->extractFieldValue($data, $mappings['email_field']);
    if ($email === '') {
      $this->logAndDisplayError('MVault handler: email field is empty or not mapped, cannot process membership.', '', $email);
      return;
    }

    $membershipId = $this->buildMembershipId($data, $mappings['membership_id_field']);

    try {
      $activeMembership = $this->mvaultClient->getActiveMembershipByEmail($email);

      if ($activeMembership !== NULL) {
        $this->handleActiveMembership($email, $membershipId);
        return;
      }

      $existingMembership = $this->mvaultClient->getMembershipByEmail($email);
      $membership = $this->buildMembership($data, $mappings, $existingMembership);

      if ($existingMembership !== NULL) {
        $result = $this->renewExistingMembership($membershipId, $membership, $existingMembership);
      }
      else {
        $result = $this->mvaultClient->createMembership($membershipId, $membership);
      }

      $this->displaySuccessMessage($result);
    }
    catch (MvaultException $e) {
      $this->logAndDisplayError(
        'MVault API error for membership @id (email: @email): @message',
        $membershipId,
        $email,
        $e,
      );
    }
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
    $this->loggerFactory->get('mvault_webform')->info(
      'MVault: active membership found for @email (id: @id), skipping creation.',
      ['@email' => $email, '@id' => $membershipId],
    );

    $this->messenger()->addWarning($this->t('@message', [
      '@message' => $this->configuration['already_active_message'],
    ]));
  }

  /**
   * Renews an existing expired membership with updated dates.
   *
   * @param string $membershipId
   *   The membership identifier.
   * @param \Drupal\mvault\ValueObject\Membership $membership
   *   The membership data with new dates.
   * @param \Drupal\mvault\ValueObject\Membership $existingMembership
   *   The existing membership retrieved from the API.
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   The updated membership returned by the API.
   */
  private function renewExistingMembership(string $membershipId, Membership $membership, Membership $existingMembership): Membership {
    return $this->mvaultClient->renewMembership(
      $membershipId,
      $membership->expireDate,
      $existingMembership,
    );
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

    $this->messenger()->addStatus($this->t('@message', [
      '@message' => $this->configuration['success_message'],
    ]));
  }

  /**
   * Builds a Membership value object from form submission data.
   *
   * @param array<string, mixed> $data
   *   The webform submission data.
   * @param array<string, string> $mappings
   *   The configured field mappings.
   * @param \Drupal\mvault\ValueObject\Membership|null $existingMembership
   *   The existing membership, if any (used to preserve the offer).
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   The membership value object populated from submission data.
   */
  private function buildMembership(array $data, array $mappings, ?Membership $existingMembership): Membership {
    $startDate = new \DateTimeImmutable('yesterday');
    $durationDays = $this->resolveDurationDays();
    $expireDate = $startDate->modify(sprintf('+%d days', $durationDays));

    $offer = $this->resolveOffer($data, $mappings['offer_id_field'], $existingMembership);
    $libraryId = $this->extractFieldValue($data, $mappings['library_id_field']);

    $additionalMetadata = $libraryId !== '' ? ['library_id' => $libraryId] : NULL;

    return new Membership(
      firstName: $this->extractFieldValue($data, $mappings['first_name_field']),
      lastName: $this->extractFieldValue($data, $mappings['last_name_field']),
      email: $this->extractFieldValue($data, $mappings['email_field']),
      offer: $offer,
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
   * Resolves the offer ID from submission data or falls back to module config.
   *
   * @param array<string, mixed> $data
   *   The webform submission data.
   * @param string $offerField
   *   The configured offer field name.
   * @param \Drupal\mvault\ValueObject\Membership|null $existingMembership
   *   The existing membership whose offer can be preserved.
   *
   * @return string
   *   The offer ID to use.
   */
  private function resolveOffer(array $data, string $offerField, ?Membership $existingMembership): string {
    $fieldOffer = $this->extractFieldValue($data, $offerField);
    if ($fieldOffer !== '') {
      return $fieldOffer;
    }

    if ($existingMembership !== NULL && $existingMembership->offer !== '') {
      return $existingMembership->offer;
    }

    return (string) ($this->configFactory->get('mvault.settings')
      ->get('default_offer_id') ?? '');
  }

  /**
   * Builds the membership ID by replacing the placeholder in the pattern.
   *
   * @param array<string, mixed> $data
   *   The webform submission data.
   * @param string $fieldKey
   *   The webform field key whose value replaces {field} in the pattern.
   *
   * @return string
   *   The generated membership ID.
   */
  private function buildMembershipId(array $data, string $fieldKey): string {
    $fieldValue = $this->extractFieldValue($data, $fieldKey);
    $pattern = (string) $this->configuration['membership_id_pattern'];

    return str_replace('{field}', $fieldValue, $pattern);
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

    return (string) ($data[$fieldKey] ?? '');
  }

  /**
   * Logs an API error and displays the configured error message to the user.
   *
   * @param string $logMessage
   *   The log message template with @placeholders.
   * @param string $membershipId
   *   The membership ID being processed.
   * @param string $email
   *   The member's email address.
   * @param \Drupal\mvault\Exception\MvaultException|null $exception
   *   The exception that was caught, if any.
   */
  private function logAndDisplayError(string $logMessage, string $membershipId, string $email, ?MvaultException $exception = NULL): void {
    $context = [
      '@id' => $membershipId,
      '@email' => $email,
      '@message' => $exception?->getMessage() ?? '',
    ];

    if ($exception !== NULL) {
      $this->loggerFactory->get('mvault_webform')
        ->error($logMessage, $context + ['exception' => $exception]);
    }
    else {
      $this->loggerFactory->get('mvault_webform')
        ->warning($logMessage, $context);
    }

    $this->messenger()->addError($this->t('@message', [
      '@message' => $this->configuration['error_message'],
    ]));
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

      $label = isset($element['#title']) ? (string) $element['#title'] : $key;
      $options[$key] = $label . ' (' . $key . ')';
    }

    return $options;
  }

}
