<?php

declare(strict_types=1);

namespace Drupal\Tests\mvault\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mvault\Form\SettingsForm;

/**
 * Tests the MVault settings form.
 *
 * @group mvault
 */
class SettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'mvault',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mvault']);
  }

  /**
   * Tests that the settings form has the correct form ID.
   */
  public function testFormIdIsCorrect(): void {
    $form = $this->container->get('form_builder')->getForm(SettingsForm::class);

    $this->assertSame('mvault_admin_settings', $form['#form_id']);
  }

  /**
   * Tests that the settings form contains all expected fields.
   */
  public function testFormContainsAllExpectedFields(): void {
    $form = $this->container->get('form_builder')->getForm(SettingsForm::class);

    $this->assertArrayHasKey('station_id', $form);
    $this->assertArrayHasKey('api_key_id', $form);
    $this->assertArrayHasKey('default_offer_id', $form);
    $this->assertArrayHasKey('membership_duration_days', $form);
  }

  /**
   * Tests that submitting the form saves plain-text credentials to api_key
   * when the Key module is not enabled.
   */
  public function testSubmitFormSavesCredentialsToApiKeyWhenKeyModuleAbsent(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'station_id' => 'WGBH',
      'api_key_id' => 'plain_key:plain_secret',
      'default_offer_id' => 'offer-123',
      'membership_duration_days' => 180,
    ]);

    /** @var \Drupal\mvault\Form\SettingsForm $settings_form */
    $settings_form = \Drupal::classResolver()->getInstanceFromDefinition(SettingsForm::class);
    $form = [];
    $settings_form->submitForm($form, $form_state);

    $config = $this->config('mvault.settings');
    $this->assertSame('WGBH', $config->get('station_id'));
    $this->assertSame('plain_key:plain_secret', $config->get('api_key'));
    $this->assertSame('', $config->get('api_key_id'));
    $this->assertSame('offer-123', $config->get('default_offer_id'));
    $this->assertSame(180, $config->get('membership_duration_days'));
  }

  /**
   * Tests that the membership_duration_days field defaults to 365.
   */
  public function testMembershipDurationDaysDefaultsTo365(): void {
    $form = $this->container->get('form_builder')->getForm(SettingsForm::class);

    $this->assertSame(365, $form['membership_duration_days']['#default_value']);
  }

  /**
   * Tests that api_key_id renders as a textfield when Key module is absent.
   */
  public function testApiKeyIdRendersAsTextfieldWhenKeyModuleAbsent(): void {
    $form = $this->container->get('form_builder')->getForm(SettingsForm::class);

    $this->assertSame('textfield', $form['api_key_id']['#type']);
  }

  /**
   * Tests that form submission saves membership_duration_days as an integer.
   */
  public function testSubmitFormSavesMembershipDurationDaysAsInteger(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'station_id' => 'WGBH',
      'api_key_id' => 'some_key',
      'default_offer_id' => 'offer-456',
      'membership_duration_days' => '90',
    ]);

    /** @var \Drupal\mvault\Form\SettingsForm $settings_form */
    $settings_form = \Drupal::classResolver()->getInstanceFromDefinition(SettingsForm::class);
    $form = [];
    $settings_form->submitForm($form, $form_state);

    $config = $this->config('mvault.settings');
    $this->assertSame(90, $config->get('membership_duration_days'));
    $this->assertIsInt($config->get('membership_duration_days'));
  }

}
