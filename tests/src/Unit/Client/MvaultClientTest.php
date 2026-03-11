<?php

declare(strict_types=1);

namespace Drupal\Tests\mvault\Unit\Client;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\mvault\Client\MvaultClient;
use Drupal\mvault\Exception\MvaultApiException;
use Drupal\mvault\Exception\MvaultNotFoundException;
use Drupal\mvault\ValueObject\Membership;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for MvaultClient.
 *
 * @coversDefaultClass \Drupal\mvault\Client\MvaultClient
 * @group mvault
 */
class MvaultClientTest extends UnitTestCase {

  private const STATION_ID = 'TEST_STATION';
  private const API_KEY_ID = 'my_key';
  private const API_CREDENTIALS = 'my_api_key:my_api_secret';
  private const BASE_URL = 'https://mvault.services.pbs.org/api';

  /** @var \GuzzleHttp\ClientInterface&\PHPUnit\Framework\MockObject\MockObject */
  private ClientInterface $httpClient;

  /** @var \Drupal\key\KeyRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
  private KeyRepositoryInterface $keyRepository;

  /** @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject */
  private ConfigFactoryInterface $configFactory;

  /** @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
  private LoggerInterface $logger;

  private MvaultClient $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Initialize Settings singleton so Settings::get() does not throw.
    new Settings([]);

    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->stubConfig(stationId: self::STATION_ID, apiKeyId: self::API_KEY_ID);
    $this->stubApiKey(self::API_CREDENTIALS);

    $this->client = new MvaultClient(
      $this->httpClient,
      $this->keyRepository,
      $this->configFactory,
      $this->logger,
    );
  }

  // ---------------------------------------------------------------------------
  // createMembership() tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::createMembership
   */
  public function testCreateMembershipSendsPutRequestToMembershipUrl(): void {
    $membershipId = 'en_12345';
    $expectedUrl = self::BASE_URL . '/stations/' . self::STATION_ID . '/memberships/' . $membershipId . '/';

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('PUT', $expectedUrl, $this->anything())
      ->willReturn($this->buildJsonResponse($this->membershipApiFixture()));

    $membership = $this->buildMembership();
    $this->client->createMembership($membershipId, $membership);
  }

  /**
   * @covers ::createMembership
   */
  public function testCreateMembershipSendsWritableFieldsInPayload(): void {
    $capturedOptions = [];
    $this->httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $membership = $this->buildMembership(additionalMetadata: ['library_id' => 'LIB-001']);
    $this->client->createMembership('en_12345', $membership);

    $json = $capturedOptions['json'];
    $this->assertArrayHasKey('first_name', $json);
    $this->assertArrayHasKey('last_name', $json);
    $this->assertArrayHasKey('email', $json);
    $this->assertArrayHasKey('offer', $json);
    $this->assertArrayHasKey('start_date', $json);
    $this->assertArrayHasKey('expire_date', $json);
    $this->assertArrayHasKey('status', $json);
    $this->assertArrayHasKey('additional_metadata', $json);
  }

  /**
   * @covers ::createMembership
   */
  public function testCreateMembershipExcludesReadOnlyFieldsFromPayload(): void {
    $capturedOptions = [];
    $this->httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $membership = $this->buildMembership(membershipId: 'en_12345', token: 'abc-token');
    $this->client->createMembership('en_12345', $membership);

    $json = $capturedOptions['json'];
    $this->assertArrayNotHasKey('membership_id', $json);
    $this->assertArrayNotHasKey('token', $json);
    $this->assertArrayNotHasKey('create_date', $json);
  }

  /**
   * @covers ::createMembership
   */
  public function testCreateMembershipReturnsMembershipObjectFromApiResponse(): void {
    $this->httpClient->method('request')
      ->willReturn($this->buildJsonResponse($this->membershipApiFixture()));

    $result = $this->client->createMembership('en_12345', $this->buildMembership());

    $this->assertSame('Jane', $result->firstName);
    $this->assertSame('en_12345', $result->membershipId);
  }

  /**
   * @covers ::createMembership
   */
  public function testCreateMembershipSendsAuthorizationHeader(): void {
    $capturedOptions = [];
    $this->httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $this->client->createMembership('en_12345', $this->buildMembership());

    $expectedToken = 'Basic ' . base64_encode(self::API_CREDENTIALS);
    $this->assertSame($expectedToken, $capturedOptions['headers']['Authorization']);
  }

  /**
   * @covers ::createMembership
   */
  public function testCreateMembershipSendsJsonContentTypeHeader(): void {
    $capturedOptions = [];
    $this->httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $this->client->createMembership('en_12345', $this->buildMembership());

    $this->assertSame('application/json', $capturedOptions['headers']['Content-Type']);
  }

  // ---------------------------------------------------------------------------
  // getMembershipByEmail() tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::getMembershipByEmail
   */
  public function testGetMembershipByEmailSendsGetRequestToEmailLookupUrl(): void {
    $email = 'jane@example.com';
    $encodedEmail = urlencode($email);
    $expectedUrl = self::BASE_URL . '/stations/' . self::STATION_ID . '/memberships/filter/email/' . $encodedEmail . '/';

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('GET', $expectedUrl, $this->anything())
      ->willReturn($this->buildJsonResponse($this->membershipApiFixture()));

    $this->client->getMembershipByEmail($email);
  }

  /**
   * @covers ::getMembershipByEmail
   */
  public function testGetMembershipByEmailReturnsMembershipOn200Response(): void {
    $this->httpClient->method('request')
      ->willReturn($this->buildJsonResponse($this->membershipApiFixture()));

    $result = $this->client->getMembershipByEmail('jane@example.com');

    $this->assertInstanceOf(Membership::class, $result);
    $this->assertSame('jane@example.com', $result->email);
  }

  /**
   * @covers ::getMembershipByEmail
   */
  public function testGetMembershipByEmailReturnsNullOn404Response(): void {
    $this->httpClient->method('request')
      ->willThrowException($this->build404Exception());

    $result = $this->client->getMembershipByEmail('unknown@example.com');

    $this->assertNull($result);
  }

  /**
   * @covers ::getMembershipByEmail
   */
  public function testGetMembershipByEmailThrowsMvaultApiExceptionOn500Response(): void {
    $this->httpClient->method('request')
      ->willThrowException($this->build500Exception());

    $this->expectException(MvaultApiException::class);
    $this->client->getMembershipByEmail('jane@example.com');
  }

  // ---------------------------------------------------------------------------
  // getActiveMembershipByEmail() tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::getActiveMembershipByEmail
   */
  public function testGetActiveMembershipByEmailSendsGetRequestToActiveEndpoint(): void {
    $email = 'jane@example.com';
    $encodedEmail = urlencode($email);
    $expectedUrl = self::BASE_URL . '/stations/' . self::STATION_ID . '/memberships/active/?email=' . $encodedEmail;

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('GET', $expectedUrl, $this->anything())
      ->willReturn($this->buildJsonResponse([$this->membershipApiFixture()]));

    $this->client->getActiveMembershipByEmail($email);
  }

  /**
   * @covers ::getActiveMembershipByEmail
   */
  public function testGetActiveMembershipByEmailReturnsNullWhenResponseArrayIsEmpty(): void {
    $this->httpClient->method('request')
      ->willReturn($this->buildJsonResponse([]));

    $result = $this->client->getActiveMembershipByEmail('jane@example.com');

    $this->assertNull($result);
  }

  /**
   * @covers ::getActiveMembershipByEmail
   */
  public function testGetActiveMembershipByEmailReturnsMembershipWhenActiveExists(): void {
    $this->httpClient->method('request')
      ->willReturn($this->buildJsonResponse([$this->membershipApiFixture()]));

    $result = $this->client->getActiveMembershipByEmail('jane@example.com');

    $this->assertInstanceOf(Membership::class, $result);
    $this->assertSame('jane@example.com', $result->email);
  }

  /**
   * @covers ::getActiveMembershipByEmail
   */
  public function testGetActiveMembershipByEmailReturnsFirstMembershipFromList(): void {
    $first = $this->membershipApiFixture(['email' => 'first@example.com', 'membership_id' => 'en_001']);
    $second = $this->membershipApiFixture(['email' => 'second@example.com', 'membership_id' => 'en_002']);

    $this->httpClient->method('request')
      ->willReturn($this->buildJsonResponse([$first, $second]));

    $result = $this->client->getActiveMembershipByEmail('first@example.com');

    $this->assertInstanceOf(Membership::class, $result);
    $this->assertSame('en_001', $result->membershipId);
  }

  /**
   * @covers ::getActiveMembershipByEmail
   */
  public function testGetActiveMembershipByEmailReturnsNullOn404Response(): void {
    $this->httpClient->method('request')
      ->willThrowException($this->build404Exception());

    $result = $this->client->getActiveMembershipByEmail('unknown@example.com');

    $this->assertNull($result);
  }

  // ---------------------------------------------------------------------------
  // renewMembership() tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::renewMembership
   */
  public function testRenewMembershipSendsPutRequestToMembershipUrl(): void {
    $membershipId = 'en_12345';
    $expectedUrl = self::BASE_URL . '/stations/' . self::STATION_ID . '/memberships/' . $membershipId . '/';

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('PUT', $expectedUrl, $this->anything())
      ->willReturn($this->buildJsonResponse($this->membershipApiFixture()));

    $existing = $this->buildMembership(membershipId: $membershipId);
    $newExpireDate = new \DateTimeImmutable('2027-01-01T00:00:00Z');
    $this->client->renewMembership($membershipId, $newExpireDate, $existing);
  }

  /**
   * @covers ::renewMembership
   */
  public function testRenewMembershipReturnsMembershipObjectFromApiResponse(): void {
    $this->httpClient->method('request')
      ->willReturn($this->buildJsonResponse($this->membershipApiFixture()));

    $existing = $this->buildMembership(membershipId: 'en_12345');
    $newExpireDate = new \DateTimeImmutable('2027-01-01T00:00:00Z');
    $result = $this->client->renewMembership('en_12345', $newExpireDate, $existing);

    $this->assertSame('Jane', $result->firstName);
  }

  /**
   * @covers ::renewMembership
   */
  public function testRenewMembershipSendsNewExpireDateInPayload(): void {
    $capturedOptions = [];
    $this->httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $existing = $this->buildMembership(membershipId: 'en_12345');
    $newExpireDate = new \DateTimeImmutable('2027-06-15T00:00:00Z');
    $this->client->renewMembership('en_12345', $newExpireDate, $existing);

    $this->assertSame('2027-06-15T00:00:00Z', $capturedOptions['json']['expire_date']);
  }

  /**
   * @covers ::renewMembership
   */
  public function testRenewMembershipThrowsMvaultNotFoundExceptionOn404Response(): void {
    $this->httpClient->method('request')
      ->willThrowException($this->build404Exception());

    $this->expectException(MvaultNotFoundException::class);

    $existing = $this->buildMembership(membershipId: 'en_12345');
    $newExpireDate = new \DateTimeImmutable('2027-01-01T00:00:00Z');
    $this->client->renewMembership('en_12345', $newExpireDate, $existing);
  }

  // ---------------------------------------------------------------------------
  // Error handling tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::createMembership
   */
  public function testApiErrorThrowsMvaultApiExceptionWithStatusCode(): void {
    $this->httpClient->method('request')
      ->willThrowException($this->build500Exception());

    $this->expectException(MvaultApiException::class);
    $this->expectExceptionMessage('MVault API returned HTTP 500');

    $this->client->createMembership('en_12345', $this->buildMembership());
  }

  /**
   * @covers ::createMembership
   */
  public function testApiErrorExceptionCarriesHttpStatusCode(): void {
    $this->httpClient->method('request')
      ->willThrowException($this->build500Exception());

    try {
      $this->client->createMembership('en_12345', $this->buildMembership());
      $this->fail('Expected MvaultApiException was not thrown.');
    }
    catch (MvaultApiException $e) {
      $this->assertSame(500, $e->getStatusCode());
    }
  }

  /**
   * @covers ::createMembership
   */
  public function testConnectionErrorThrowsMvaultApiExceptionWithStatusCodeZero(): void {
    $connectionError = new TransferException('cURL error: Could not connect');
    $this->httpClient->method('request')
      ->willThrowException($connectionError);

    try {
      $this->client->createMembership('en_12345', $this->buildMembership());
      $this->fail('Expected MvaultApiException was not thrown.');
    }
    catch (MvaultApiException $e) {
      $this->assertSame(0, $e->getStatusCode());
    }
  }

  /**
   * @covers ::createMembership
   */
  public function testConnectionErrorLogsMessage(): void {
    $connectionError = new TransferException('cURL error: Could not connect');
    $this->httpClient->method('request')
      ->willThrowException($connectionError);

    $this->logger->expects($this->once())
      ->method('error');

    try {
      $this->client->createMembership('en_12345', $this->buildMembership());
    }
    catch (MvaultApiException) {
      // Expected.
    }
  }

  /**
   * @covers ::createMembership
   */
  public function testApiErrorLogsMessage(): void {
    $this->httpClient->method('request')
      ->willThrowException($this->build500Exception());

    $this->logger->expects($this->once())
      ->method('error');

    try {
      $this->client->createMembership('en_12345', $this->buildMembership());
    }
    catch (MvaultApiException) {
      // Expected.
    }
  }

  // ---------------------------------------------------------------------------
  // Optional Key module / plain-text fallback tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::createMembership
   */
  public function testCredentialsReadFromPlainTextConfigWhenKeyRepositoryIsNull(): void {
    $plainTextCredentials = 'plain_key:plain_secret';

    $httpClient = $this->createMock(ClientInterface::class);
    $configFactory = $this->buildConfigFactory(stationId: self::STATION_ID, apiKeyId: '', apiKey: $plainTextCredentials);
    $logger = $this->createMock(LoggerInterface::class);

    $client = new MvaultClient($httpClient, NULL, $configFactory, $logger);

    $capturedOptions = [];
    $httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $client->createMembership('en_12345', $this->buildMembership());

    $expectedToken = 'Basic ' . base64_encode($plainTextCredentials);
    $this->assertSame($expectedToken, $capturedOptions['headers']['Authorization']);
  }

  /**
   * @covers ::createMembership
   */
  public function testWarningIsLoggedWhenNoCredentialsConfigured(): void {
    $httpClient = $this->createMock(ClientInterface::class);
    $configFactory = $this->buildConfigFactory(stationId: self::STATION_ID, apiKeyId: '', apiKey: '');
    $logger = $this->createMock(LoggerInterface::class);

    $client = new MvaultClient($httpClient, NULL, $configFactory, $logger);

    $httpClient->method('request')
      ->willReturn($this->buildJsonResponse($this->membershipApiFixture()));

    $logger->expects($this->once())
      ->method('warning');

    $client->createMembership('en_12345', $this->buildMembership());
  }

  /**
   * @covers ::createMembership
   */
  public function testKeyModuleTakesPrecedenceOverPlainTextConfig(): void {
    $plainTextCredentials = 'plain_key:plain_secret';

    $httpClient = $this->createMock(ClientInterface::class);
    $configFactory = $this->buildConfigFactory(stationId: self::STATION_ID, apiKeyId: self::API_KEY_ID, apiKey: $plainTextCredentials);
    $logger = $this->createMock(LoggerInterface::class);

    $keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn(self::API_CREDENTIALS);
    $keyRepository->method('getKey')->with(self::API_KEY_ID)->willReturn($key);

    $client = new MvaultClient($httpClient, $keyRepository, $configFactory, $logger);

    $capturedOptions = [];
    $httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $client->createMembership('en_12345', $this->buildMembership());

    $expectedToken = 'Basic ' . base64_encode(self::API_CREDENTIALS);
    $this->assertSame($expectedToken, $capturedOptions['headers']['Authorization']);
  }

  /**
   * @covers ::createMembership
   */
  public function testWarningIsLoggedWhenKeyIdConfiguredButKeyEntityNotFoundInRepository(): void {
    $plainTextFallback = 'fallback_key:fallback_secret';

    $httpClient = $this->createMock(ClientInterface::class);
    $configFactory = $this->buildConfigFactory(stationId: self::STATION_ID, apiKeyId: self::API_KEY_ID, apiKey: $plainTextFallback);
    $logger = $this->createMock(LoggerInterface::class);

    // Repository is present but returns NULL for the configured key ID.
    $keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $keyRepository->method('getKey')->with(self::API_KEY_ID)->willReturn(NULL);

    $client = new MvaultClient($httpClient, $keyRepository, $configFactory, $logger);

    $httpClient->method('request')
      ->willReturn($this->buildJsonResponse($this->membershipApiFixture()));

    $logger->expects($this->once())
      ->method('warning');

    $client->createMembership('en_12345', $this->buildMembership());
  }

  /**
   * @covers ::createMembership
   */
  public function testPlainTextFallbackIsUsedWhenKeyIdConfiguredButKeyEntityNotFoundInRepository(): void {
    $plainTextFallback = 'fallback_key:fallback_secret';

    $httpClient = $this->createMock(ClientInterface::class);
    $configFactory = $this->buildConfigFactory(stationId: self::STATION_ID, apiKeyId: self::API_KEY_ID, apiKey: $plainTextFallback);
    $logger = $this->createMock(LoggerInterface::class);

    // Repository is present but returns NULL for the configured key ID,
    // so the client must fall back to the plain-text api_key value.
    $keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $keyRepository->method('getKey')->with(self::API_KEY_ID)->willReturn(NULL);

    $client = new MvaultClient($httpClient, $keyRepository, $configFactory, $logger);

    $capturedOptions = [];
    $httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
        $capturedOptions = $options;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $client->createMembership('en_12345', $this->buildMembership());

    $expectedToken = 'Basic ' . base64_encode($plainTextFallback);
    $this->assertSame($expectedToken, $capturedOptions['headers']['Authorization']);
  }

  // ---------------------------------------------------------------------------
  // Settings override tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::createMembership
   */
  public function testRequestUsesSettingsPhpBaseUrlOverrideWhenSet(): void {
    // Re-initialize Settings with a base URL override.
    new Settings(['mvault.base_url' => 'https://custom.example.com/api']);

    $capturedUrl = '';
    $this->httpClient->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedUrl) {
        $capturedUrl = $url;
        return $this->buildJsonResponse($this->membershipApiFixture());
      });

    $this->client->createMembership('en_12345', $this->buildMembership());

    $this->assertStringStartsWith('https://custom.example.com/api', $capturedUrl);

    // Restore default empty Settings for subsequent tests.
    new Settings([]);
  }

  // ---------------------------------------------------------------------------
  // Private helper methods
  // ---------------------------------------------------------------------------

  /**
   * Sets up a Config mock returning station_id, api_key_id, and api_key.
   *
   * @param string $stationId
   *   The station ID to return from config.
   * @param string $apiKeyId
   *   The API key ID to return from config.
   * @param string $apiKey
   *   The plain-text API key to return from config.
   */
  private function stubConfig(string $stationId, string $apiKeyId, string $apiKey = ''): void {
    $config = $this->createMock(Config::class);
    $config->method('get')
      ->willReturnCallback(static function (string $key) use ($stationId, $apiKeyId, $apiKey): mixed {
        return match ($key) {
          'station_id' => $stationId,
          'api_key_id' => $apiKeyId,
          'api_key' => $apiKey,
          default => NULL,
        };
      });

    $this->configFactory->method('get')
      ->with('mvault.settings')
      ->willReturn($config);
  }

  /**
   * Builds a standalone ConfigFactoryInterface mock for tests that need fresh
   * collaborators independent of the class-level setUp() mocks.
   *
   * @param string $stationId
   *   The station ID to return from config.
   * @param string $apiKeyId
   *   The API key ID to return from config.
   * @param string $apiKey
   *   The plain-text API key to return from config.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The mocked config factory.
   */
  private function buildConfigFactory(string $stationId, string $apiKeyId, string $apiKey): ConfigFactoryInterface {
    $config = $this->createMock(Config::class);
    $config->method('get')
      ->willReturnCallback(static function (string $key) use ($stationId, $apiKeyId, $apiKey): mixed {
        return match ($key) {
          'station_id' => $stationId,
          'api_key_id' => $apiKeyId,
          'api_key' => $apiKey,
          default => NULL,
        };
      });

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('mvault.settings')
      ->willReturn($config);

    return $configFactory;
  }

  /**
   * Sets up the Key repository mock to return a key with the given value.
   *
   * @param string $keyValue
   *   The key value to return (e.g. "api_key:api_secret").
   */
  private function stubApiKey(string $keyValue): void {
    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn($keyValue);

    $this->keyRepository->method('getKey')
      ->with(self::API_KEY_ID)
      ->willReturn($key);
  }

  /**
   * Builds a PSR-7 response mock with a JSON body.
   *
   * @param array<mixed> $data
   *   The data to serialize as JSON.
   *
   * @return \Psr\Http\Message\ResponseInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The mocked response.
   */
  private function buildJsonResponse(array $data): ResponseInterface {
    $body = $this->createMock(StreamInterface::class);
    $body->method('getContents')->willReturn(json_encode($data, JSON_THROW_ON_ERROR));

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getBody')->willReturn($body);

    return $response;
  }

  /**
   * Builds a ClientException that simulates a 404 response.
   *
   * @return \GuzzleHttp\Exception\ClientException
   *   The exception.
   */
  private function build404Exception(): ClientException {
    $request = $this->createMock(RequestInterface::class);
    $responseBody = $this->createMock(StreamInterface::class);
    $responseBody->method('getContents')->willReturn('');

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(404);
    $response->method('getBody')->willReturn($responseBody);

    return new ClientException('Not Found', $request, $response);
  }

  /**
   * Builds a ClientException that simulates a 500 response.
   *
   * @return \GuzzleHttp\Exception\ClientException
   *   The exception.
   */
  private function build500Exception(): ClientException {
    $request = $this->createMock(RequestInterface::class);
    $responseBody = $this->createMock(StreamInterface::class);
    $responseBody->method('getContents')->willReturn('Internal Server Error');

    $response = $this->createMock(ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(500);
    $response->method('getBody')->willReturn($responseBody);

    return new ClientException('Internal Server Error', $request, $response);
  }

  /**
   * Returns a full API response fixture for a membership.
   *
   * @param array<string, mixed> $overrides
   *   Optional field overrides.
   *
   * @return array<string, mixed>
   *   The fixture data.
   */
  private function membershipApiFixture(array $overrides = []): array {
    return array_merge([
      'membership_id' => 'en_12345',
      'first_name' => 'Jane',
      'last_name' => 'Doe',
      'email' => 'jane@example.com',
      'offer' => 'PASSPORT_MONTHLY',
      'start_date' => '2025-01-01T00:00:00Z',
      'expire_date' => '2026-01-01T00:00:00Z',
      'status' => 'On',
      'token' => 'abc-token-xyz',
    ], $overrides);
  }

  /**
   * Builds a Membership value object with sensible defaults.
   *
   * @param string $firstName
   *   First name.
   * @param string $lastName
   *   Last name.
   * @param string $email
   *   Email address.
   * @param string $offer
   *   Offer identifier.
   * @param \DateTimeImmutable|null $startDate
   *   Start date.
   * @param \DateTimeImmutable|null $expireDate
   *   Expire date.
   * @param string|null $membershipId
   *   Membership ID.
   * @param string|null $token
   *   Activation token.
   * @param array<string, mixed>|null $additionalMetadata
   *   Additional metadata.
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   The membership.
   */
  private function buildMembership(
    string $firstName = 'Jane',
    string $lastName = 'Doe',
    string $email = 'jane@example.com',
    string $offer = 'PASSPORT_MONTHLY',
    ?\DateTimeImmutable $startDate = NULL,
    ?\DateTimeImmutable $expireDate = NULL,
    ?string $membershipId = NULL,
    ?string $token = NULL,
    ?array $additionalMetadata = NULL,
  ): Membership {
    return new Membership(
      firstName: $firstName,
      lastName: $lastName,
      email: $email,
      offer: $offer,
      startDate: $startDate ?? new \DateTimeImmutable('2025-01-01T00:00:00Z'),
      expireDate: $expireDate ?? new \DateTimeImmutable('2026-01-01T00:00:00Z'),
      membershipId: $membershipId,
      status: 'On',
      token: $token,
      additionalMetadata: $additionalMetadata,
    );
  }

}
