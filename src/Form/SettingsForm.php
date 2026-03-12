<?php

declare(strict_types=1);

namespace Drupal\mvault\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the MVault administration settings form.
 */
class SettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mvault_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('mvault.settings');

    $form['station_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Station ID'),
      '#description' => $this->t('Your PBS station identifier (e.g. WGBH).'),
      '#default_value' => $config->get('station_id'),
      '#required' => TRUE,
    ];

    $form['api_key_id'] = $this->buildApiKeyField((string) ($config->get('api_key_id') ?? ''));

    $form['default_offer_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Offer ID'),
      '#description' => $this->t('The default MVault offer ID used when creating memberships.'),
      '#default_value' => $config->get('default_offer_id'),
    ];

    $form['membership_duration_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Membership Duration (days)'),
      '#description' => $this->t('Number of days a membership remains active after creation or renewal.'),
      '#default_value' => $config->get('membership_duration_days') ?? 365,
      '#min' => 1,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('mvault.settings')
      ->set('station_id', $form_state->getValue('station_id'))
      ->set('default_offer_id', $form_state->getValue('default_offer_id'))
      ->set('membership_duration_days', (int) $form_state->getValue('membership_duration_days'));

    if (\Drupal::moduleHandler()->moduleExists('key')) {
      $config->set('api_key_id', $form_state->getValue('api_key_id'))
        ->set('api_key', '');
    } else {
      $config->set('api_key', $form_state->getValue('api_key_id'))
        ->set('api_key_id', '');
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['mvault.settings'];
  }

  /**
   * Builds the API key field, using key_select if the Key module is available.
   *
   * @param string $defaultValue
   *   The current configured key ID.
   *
   * @return array<string, mixed>
   *   A Form API element definition.
   */
  private function buildApiKeyField(string $defaultValue): array {
    $base = [
      '#title' => $this->t('API Key'),
      '#description' => $this->t('The Key entity holding the MVault API credentials in "api_key:api_secret" format.'),
      '#default_value' => $defaultValue,
      '#required' => TRUE,
    ];

    if (\Drupal::moduleHandler()->moduleExists('key')) {
      return $base + ['#type' => 'key_select'];
    }

    return $base + ['#type' => 'textfield'];
  }
}
