<?php

declare(strict_types=1);

namespace Drupal\Tests\mvault\Kernel\Plugin\WebformHandler;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mvault\Client\MvaultClientInterface;
use Drupal\mvault\Exception\MvaultApiException;
use Drupal\mvault\ValueObject\Membership;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Kernel tests for the MvaultWebformHandler plugin.
 *
 * Tests handler configuration persistence and postSave() business logic using
 * a mocked MvaultClient injected into the service container before handler
 * instantiation.
 *
 * @group mvault
 * @group mvault_webform
 */
class MvaultWebformHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'field',
    'webform',
    'mvault',
    'mvault_webform',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['webform', 'mvault']);
    $this->installEntitySchema('webform_submission');
    $this->installEntitySchema('user');
  }

  // -------------------------------------------------------------------------
  // Handler configuration tests
  // -------------------------------------------------------------------------

  /**
   * Tests that handler settings persist after saving and reloading the webform.
   */
  public function testHandlerSettingsPersistAfterSaveAndReload(): void {
    $webform = $this->createTestWebform();

    /** @var \Drupal\webform\Plugin\WebformHandlerManagerInterface $handler_manager */
    $handler_manager = $this->container->get('plugin.manager.webform.handler');

    /** @var \Drupal\mvault_webform\Plugin\WebformHandler\MvaultWebformHandler $handler */
    $handler = $handler_manager->createInstance('mvault_membership', [
      'id' => 'mvault_membership',
      'handler_id' => 'mvault_membership',
      'label' => 'MVault Membership',
      'notes' => '',
      'status' => 1,
      'conditions' => [],
      'weight' => 0,
      'settings' => [
        'membership_id_field' => 'supporter_id',
        'field_mappings' => [
          'first_name_field' => 'first_name',
          'last_name_field' => 'last_name',
          'email_field' => 'email',
          'library_id_field' => 'library_id',
        ],
        'membership_id_pattern' => 'lib_{field}',
        'membership_duration_days' => 730,
        'success_message' => 'Membership activated!',
        'already_active_message' => 'Already active.',
        'error_message' => 'An error occurred.',
      ],
    ]);

    $handler->setWebform($webform);
    $webform->addWebformHandler($handler);
    $webform->save();

    // Reload from storage to confirm persistence.
    /** @var \Drupal\webform\WebformInterface $reloaded */
    $reloaded = Webform::load($webform->id());
    $this->assertNotNull($reloaded, 'Webform must be loadable after save.');

    $reloadedHandler = $reloaded->getHandler('mvault_membership');
    $this->assertNotNull($reloadedHandler, 'mvault_membership handler must be present on reloaded webform.');

    $settings = $reloadedHandler->getConfiguration()['settings'];

    $this->assertSame('lib_{field}', $settings['membership_id_pattern']);
    $this->assertSame(730, $settings['membership_duration_days']);
    $this->assertSame('supporter_id', $settings['membership_id_field']);
    $this->assertSame('email', $settings['field_mappings']['email_field']);
    $this->assertSame('Membership activated!', $settings['success_message']);
  }

  // -------------------------------------------------------------------------
  // postSave() — new membership scenario
  // -------------------------------------------------------------------------

  /**
   * Tests that postSave() calls createMembership() when no existing membership.
   */
  public function testPostSaveCallsCreateMembershipWhenNoExistingMembership(): void {
    $createdMembership = $this->buildMembershipFixture('en_sup-001', 'abc-token-123');

    $mockClient = $this->createMock(MvaultClientInterface::class);
    $mockClient->expects($this->once())
      ->method('getActiveMembershipByEmail')
      ->with('jane@example.com')
      ->willReturn(NULL);

    $mockClient->expects($this->once())
      ->method('getMembershipByEmail')
      ->with('jane@example.com')
      ->willReturn(NULL);

    $mockClient->expects($this->once())
      ->method('createMembership')
      ->with(
        $this->identicalTo('en_sup-001'),
        $this->isInstanceOf(Membership::class),
      )
      ->willReturn($createdMembership);

    $mockClient->expects($this->never())
      ->method('renewMembership');

    // Register the mock BEFORE creating the webform so the handler picks it up.
    $this->container->set('mvault.client', $mockClient);

    $webform = $this->createHandlerWebform();
    $this->submitWebform($webform, [
      'email' => 'jane@example.com',
      'first_name' => 'Jane',
      'last_name' => 'Doe',
      'supporter_id' => 'sup-001',
    ]);
  }

  // -------------------------------------------------------------------------
  // postSave() — active membership scenario
  // -------------------------------------------------------------------------

  /**
   * Tests that postSave() skips creation when an active membership is found.
   */
  public function testPostSaveSkipsCreationWhenActiveMembershipExists(): void {
    $activeMembership = $this->buildMembershipFixture('existing-mem', NULL);

    $mockClient = $this->createMock(MvaultClientInterface::class);
    $mockClient->expects($this->once())
      ->method('getActiveMembershipByEmail')
      ->with('active@example.com')
      ->willReturn($activeMembership);

    $mockClient->expects($this->never())
      ->method('createMembership');

    $mockClient->expects($this->never())
      ->method('renewMembership');

    $this->container->set('mvault.client', $mockClient);

    $webform = $this->createHandlerWebform();
    $this->submitWebform($webform, [
      'email' => 'active@example.com',
      'first_name' => 'Active',
      'last_name' => 'User',
      'supporter_id' => 'sup-active',
    ]);

    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $this->container->get('messenger');
    $messages = $messenger->all();

    $warningMessages = $messages['warning'] ?? [];
    $this->assertNotEmpty($warningMessages, 'A warning message must be displayed when membership is already active.');
  }

  // -------------------------------------------------------------------------
  // postSave() — expired membership renewal scenario
  // -------------------------------------------------------------------------

  /**
   * Tests that postSave() calls renewMembership() when an expired membership exists.
   */
  public function testPostSaveCallsRenewMembershipWhenExpiredMembershipExists(): void {
    $expiredMembership = $this->buildMembershipFixture('exp-mem-001', NULL);
    $renewedMembership = $this->buildMembershipFixture('exp-mem-001', NULL);

    $mockClient = $this->createMock(MvaultClientInterface::class);
    $mockClient->expects($this->once())
      ->method('getActiveMembershipByEmail')
      ->with('expired@example.com')
      ->willReturn(NULL);

    $mockClient->expects($this->once())
      ->method('getMembershipByEmail')
      ->with('expired@example.com')
      ->willReturn($expiredMembership);

    $mockClient->expects($this->never())
      ->method('createMembership');

    $mockClient->expects($this->once())
      ->method('renewMembership')
      ->with(
        $this->identicalTo('en_sup-expired'),
        $this->isInstanceOf(\DateTimeImmutable::class),
        $this->isInstanceOf(Membership::class),
      )
      ->willReturn($renewedMembership);

    $this->container->set('mvault.client', $mockClient);

    $webform = $this->createHandlerWebform();
    $this->submitWebform($webform, [
      'email' => 'expired@example.com',
      'first_name' => 'Expired',
      'last_name' => 'User',
      'supporter_id' => 'sup-expired',
    ]);
  }

  // -------------------------------------------------------------------------
  // postSave() — error handling
  // -------------------------------------------------------------------------

  /**
   * Tests that postSave() does not propagate MvaultApiException to the caller.
   *
   * A submission must complete successfully even when the MVault API fails.
   */
  public function testPostSaveDoesNotPropagateApiException(): void {
    $mockClient = $this->createMock(MvaultClientInterface::class);
    $mockClient->method('getActiveMembershipByEmail')
      ->willThrowException(new MvaultApiException(
        message: 'MVault API returned HTTP 503',
        statusCode: 503,
        responseBody: 'Service Unavailable',
      ));

    $this->container->set('mvault.client', $mockClient);

    $webform = $this->createHandlerWebform();

    // If the exception propagates, this line would never be reached.
    $submission = $this->submitWebform($webform, [
      'email' => 'error@example.com',
      'first_name' => 'Error',
      'last_name' => 'User',
      'supporter_id' => 'sup-error',
    ]);

    $this->assertNotNull($submission->id(), 'Webform submission must be persisted even when API throws MvaultApiException.');
  }

  /**
   * Tests that an error message is displayed when an API exception occurs.
   */
  public function testPostSaveDisplaysErrorMessageWhenApiThrows(): void {
    $mockClient = $this->createMock(MvaultClientInterface::class);
    $mockClient->method('getActiveMembershipByEmail')
      ->willThrowException(new MvaultApiException(
        message: 'MVault API returned HTTP 500',
        statusCode: 500,
        responseBody: 'Internal Server Error',
      ));

    $this->container->set('mvault.client', $mockClient);

    $webform = $this->createHandlerWebform();
    $this->submitWebform($webform, [
      'email' => 'fail@example.com',
      'first_name' => 'Fail',
      'last_name' => 'User',
      'supporter_id' => 'sup-fail',
    ]);

    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $this->container->get('messenger');
    $messages = $messenger->all();

    $errorMessages = $messages['error'] ?? [];
    $this->assertNotEmpty($errorMessages, 'An error message must be shown to the user when the API call fails.');
  }

  // -------------------------------------------------------------------------
  // postSave() — regression: corrupted configuration safeguards
  // -------------------------------------------------------------------------

  /**
   * Tests that postSave() returns early and logs a warning when field_mappings
   * is empty, without throwing a TypeError or calling the API client.
   *
   * Regression: guards against configuration corruption where field_mappings
   * is stored as an empty array.
   */
  public function testPostSaveHandlesNullFieldMappingsGracefully(): void {
    $mockClient = $this->createMock(MvaultClientInterface::class);
    $mockClient->expects($this->never())->method('getActiveMembershipByEmail');
    $mockClient->expects($this->never())->method('getMembershipByEmail');
    $mockClient->expects($this->never())->method('createMembership');
    $mockClient->expects($this->never())->method('renewMembership');

    $this->container->set('mvault.client', $mockClient);

    $webform = $this->createHandlerWebformWithFieldMappings(membershipIdField: '', fieldMappings: []);

    // No exception should be thrown; submission must complete successfully.
    $submission = $this->submitWebform($webform, [
      'email' => 'test@example.com',
      'first_name' => 'Test',
      'last_name' => 'User',
      'supporter_id' => 'sup-001',
    ]);

    $this->assertNotNull($submission->id(), 'Webform submission must be persisted when field_mappings is empty.');
  }

  /**
   * Tests that postSave() returns early and only logs a warning when
   * email_field is empty, without calling the API client or displaying
   * a user-facing message.
   *
   * Regression: guards against configuration where field_mappings is present
   * but email_field is not configured (empty string or null). In postSave()
   * context the form is already submitted, so only logging is appropriate.
   */
  public function testPostSaveHandlesNullEmailFieldGracefully(): void {
    $mockClient = $this->createMock(MvaultClientInterface::class);
    $mockClient->expects($this->never())->method('getActiveMembershipByEmail');
    $mockClient->expects($this->never())->method('getMembershipByEmail');
    $mockClient->expects($this->never())->method('createMembership');
    $mockClient->expects($this->never())->method('renewMembership');

    $this->container->set('mvault.client', $mockClient);

    $webform = $this->createHandlerWebformWithFieldMappings(
      membershipIdField: 'supporter_id',
      fieldMappings: [
        'first_name_field' => 'first_name',
        'last_name_field' => 'last_name',
        'email_field' => '',
        'library_id_field' => '',
      ],
    );

    // No exception should be thrown; submission must complete successfully.
    $submission = $this->submitWebform($webform, [
      'email' => 'test@example.com',
      'first_name' => 'Test',
      'last_name' => 'User',
      'supporter_id' => 'sup-001',
    ]);

    $this->assertNotNull($submission->id(), 'Webform submission must be persisted when email_field is not configured.');
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /**
   * Creates a minimal webform with the fields used in handler tests.
   *
   * @return \Drupal\webform\WebformInterface
   *   The saved webform entity.
   */
  private function createTestWebform(): WebformInterface {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::create([
      'id' => 'test_mvault_' . $this->randomMachineName(8),
      'title' => 'Test MVault Webform',
    ]);

    $webform->setElements([
      'email' => [
        '#type' => 'email',
        '#title' => 'Email',
      ],
      'first_name' => [
        '#type' => 'textfield',
        '#title' => 'First name',
      ],
      'last_name' => [
        '#type' => 'textfield',
        '#title' => 'Last name',
      ],
      'supporter_id' => [
        '#type' => 'textfield',
        '#title' => 'Supporter ID',
      ],
    ]);

    $webform->save();

    return $webform;
  }

  /**
   * Creates a webform with the MVault handler attached and configured.
   *
   * The handler uses `en_{field}` as the membership ID pattern and maps
   * `supporter_id` -> membership ID field, `email` -> email field.
   *
   * @return \Drupal\webform\WebformInterface
   *   The saved webform entity with the MVault handler.
   */
  private function createHandlerWebform(): WebformInterface {
    $webform = $this->createTestWebform();

    /** @var \Drupal\webform\Plugin\WebformHandlerManagerInterface $handler_manager */
    $handler_manager = $this->container->get('plugin.manager.webform.handler');

    /** @var \Drupal\mvault_webform\Plugin\WebformHandler\MvaultWebformHandler $handler */
    $handler = $handler_manager->createInstance('mvault_membership', [
      'id' => 'mvault_membership',
      'handler_id' => 'mvault_membership',
      'label' => 'MVault Membership',
      'notes' => '',
      'status' => 1,
      'conditions' => [],
      'weight' => 0,
      'settings' => [
        'membership_id_field' => 'supporter_id',
        'field_mappings' => [
          'first_name_field' => 'first_name',
          'last_name_field' => 'last_name',
          'email_field' => 'email',
          'library_id_field' => '',
        ],
        'membership_id_pattern' => 'en_{field}',
        'membership_duration_days' => 365,
        'success_message' => 'Your PBS Passport membership has been activated. Thank you!',
        'already_active_message' => 'You already have an active PBS Passport membership and are not eligible for this offer.',
        'error_message' => 'We were unable to process your membership at this time. Please contact support.',
      ],
    ]);

    $handler->setWebform($webform);
    $webform->addWebformHandler($handler);
    $webform->save();

    return $webform;
  }

  /**
   * Creates and saves a WebformSubmission to trigger postSave() handlers.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform to submit against.
   * @param array<string, string> $data
   *   The submission field values.
   *
   * @return \Drupal\webform\WebformSubmissionInterface
   *   The saved submission entity.
   */
  private function submitWebform(WebformInterface $webform, array $data): WebformSubmissionInterface {
    $submission = WebformSubmission::create([
      'webform_id' => $webform->id(),
      'data' => $data,
    ]);

    $submission->save();

    return $submission;
  }

  /**
   * Creates a webform with the MVault handler configured with custom mappings.
   *
   * Used to simulate configuration corruption or misconfiguration scenarios
   * where field_mappings differs from the standard handler setup.
   *
   * @param string $membershipIdField
   *   The membership_id_field value to inject into the handler settings.
   * @param array<string, string> $fieldMappings
   *   The field_mappings array to inject into the handler settings.
   *
   * @return \Drupal\webform\WebformInterface
   *   The saved webform entity with the MVault handler.
   */
  private function createHandlerWebformWithFieldMappings(string $membershipIdField, array $fieldMappings): WebformInterface {
    $webform = $this->createTestWebform();

    /** @var \Drupal\webform\Plugin\WebformHandlerManagerInterface $handler_manager */
    $handler_manager = $this->container->get('plugin.manager.webform.handler');

    /** @var \Drupal\mvault_webform\Plugin\WebformHandler\MvaultWebformHandler $handler */
    $handler = $handler_manager->createInstance('mvault_membership', [
      'id' => 'mvault_membership',
      'handler_id' => 'mvault_membership',
      'label' => 'MVault Membership',
      'notes' => '',
      'status' => 1,
      'conditions' => [],
      'weight' => 0,
      'settings' => [
        'membership_id_field' => $membershipIdField,
        'field_mappings' => $fieldMappings,
        'membership_id_pattern' => 'en_{field}',
        'membership_duration_days' => 365,
        'success_message' => 'Your PBS Passport membership has been activated. Thank you!',
        'already_active_message' => 'You already have an active PBS Passport membership and are not eligible for this offer.',
        'error_message' => 'We were unable to process your membership at this time. Please contact support.',
      ],
    ]);

    $handler->setWebform($webform);
    $webform->addWebformHandler($handler);
    $webform->save();

    return $webform;
  }

  /**
   * Builds a Membership value object for use as a mock return value.
   *
   * @param string $membershipId
   *   The membership ID.
   * @param string|null $token
   *   The activation token, or NULL if not present.
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   A populated Membership value object.
   */
  private function buildMembershipFixture(string $membershipId, ?string $token): Membership {
    return new Membership(
      firstName: 'Test',
      lastName: 'User',
      email: 'test@example.com',
      offer: 'OFFER-001',
      startDate: new \DateTimeImmutable('yesterday'),
      expireDate: new \DateTimeImmutable('+1 year'),
      membershipId: $membershipId,
      status: 'On',
      token: $token,
    );
  }

}
