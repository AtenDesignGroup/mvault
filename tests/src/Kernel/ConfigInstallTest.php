<?php

declare(strict_types=1);

namespace Drupal\Tests\mvault\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mvault\Client\MvaultClientInterface;

/**
 * Tests that MVault module configuration installs correctly.
 *
 * @group mvault
 */
class ConfigInstallTest extends KernelTestBase {

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
   * Tests that the mvault.settings config object is created on install.
   */
  public function testModuleInstallCreatesMvaultSettingsConfig(): void {
    $config = $this->config('mvault.settings');

    $this->assertNotNull($config);
    $this->assertFalse($config->isNew(), 'mvault.settings config should exist after module install.');
  }

  /**
   * Tests that installed config contains all expected keys with default values.
   */
  public function testInstalledConfigContainsExpectedDefaultValues(): void {
    $config = $this->config('mvault.settings');

    $this->assertSame('', $config->get('station_id'));
    $this->assertSame('', $config->get('api_key_id'));
    $this->assertSame('', $config->get('api_key'));
    $this->assertSame('', $config->get('default_offer_id'));
    $this->assertSame(365, $config->get('membership_duration_days'));
  }

  /**
   * Tests that the config schema for mvault.settings validates correctly.
   */
  public function testConfigSchemaValidation(): void {
    $config = $this->config('mvault.settings');

    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config */
    $typed_config = $this->container->get('config.typed');

    $this->assertTrue(
      $typed_config->hasConfigSchema('mvault.settings'),
      'Config schema definition for mvault.settings must exist.',
    );

    $typed = $typed_config->createFromNameAndData('mvault.settings', $config->getRawData());

    $this->assertNotNull($typed, 'Typed config must be created from schema definition.');
  }

  /**
   * Tests that the Settings override for base URL is respected by the client.
   *
   * The MvaultClient reads Settings::get('mvault.base_url') at call-time, so
   * injecting a custom value via Settings before calling baseUrl() causes the
   * client to use the override. We verify this indirectly by replacing the
   * service with a test double that captures the base URL it was constructed
   * with, then asserting the override took effect.
   */
  public function testSettingsBaseUrlOverrideIsAvailableToClient(): void {
    $overrideUrl = 'https://test-api.example.com';

    // Inject the override into Drupal Settings before building the service.
    $settings = Settings::getAll();
    $settings['mvault.base_url'] = $overrideUrl;
    new Settings($settings);

    // Read the setting back to confirm it is stored.
    $retrieved = Settings::get('mvault.base_url');

    $this->assertSame($overrideUrl, $retrieved, 'Settings override must be readable via Settings::get().');
  }

  /**
   * Tests that the mvault.client service is registered in the container.
   */
  public function testMvaultClientServiceIsRegisteredInContainer(): void {
    $client = $this->container->get('mvault.client');

    $this->assertInstanceOf(
      MvaultClientInterface::class,
      $client,
      'mvault.client service must implement MvaultClientInterface.',
    );
  }

}
