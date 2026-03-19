<?php

declare(strict_types=1);

namespace Drupal\mvault\Client;

use Psr\Log\LoggerInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\mvault\ValueObject\Membership;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\mvault\Exception\MvaultApiException;
use Drupal\mvault\Exception\MvaultNotFoundException;

/**
 * HTTP client for the PBS MVault API.
 */
class MvaultClient implements MvaultClientInterface {
  /** The default MVault API base URL. */
  private const string DEFAULT_BASE_URL = 'https://mvault.services.pbs.org/api';

  /**
   * Constructs a MvaultClient.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\key\KeyRepositoryInterface|null $keyRepository
   *   The Key module repository for retrieving credentials, or NULL when the
   *   Key module is not installed.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly ?KeyRepositoryInterface $keyRepository,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function createMembership(
    string $membershipId,
    Membership $membership,
  ): Membership {
    return Membership::fromApiResponse($this->requestObject(
      'PUT',
      $this->membershipUrl($membershipId),
      $membership->toApiPayload(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getMembershipById(string $membershipId): ?Membership {
    try {
      return Membership::fromApiResponse(
        $this->requestObject('GET', $this->membershipUrl($membershipId)),
      );
    } catch (MvaultNotFoundException) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMembershipByEmail(string $email): ?Membership {
    try {
      $items = $this->requestObject(
        'GET',
        $this->emailLookupUrl($email),
      );

      if (empty($items)) {
        return NULL;
      }

      return Membership::fromApiResponse($items['objects'][0]);
    } catch (MvaultNotFoundException) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveMembershipByEmail(string $email): ?Membership {
    try {
      $items = $this->requestList(
        'GET',
        $this->activeEmailLookupUrl($email),
      );

      if (empty($items)) {
        return NULL;
      }

      return Membership::fromApiResponse($items[0]);
    } catch (MvaultNotFoundException) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function renewMembership(
    string $membershipId,
    \DateTimeImmutable $newExpireDate,
    Membership $existingMembership,
  ): Membership {
    $payload = [
      'email' => $existingMembership->email,
      'first_name' => $existingMembership->firstName,
      'last_name' => $existingMembership->lastName,
      'start_date' => $existingMembership->startDate->format('Y-m-d\TH:i:s\Z'),
      'expire_date' => $newExpireDate->format('Y-m-d\TH:i:s\Z'),
      'status' => 'On',
    ];

    $data = $this->requestObject(
      'PUT',
      $this->membershipUrl($membershipId),
      $payload,
    );

    return Membership::fromApiResponse($data);
  }

  /**
   * Executes an HTTP request and returns the decoded response as an
   * associative array.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $url
   *   The full request URL.
   * @param array<string, mixed> $payload
   *   The request payload for write operations.
   *
   * @throws \Drupal\mvault\Exception\MvaultNotFoundException
   *   When the API returns 404.
   * @throws \Drupal\mvault\Exception\MvaultApiException
   * @throws \JsonException
   *   When the API returns any other error status code.
   *
   * @return array<string, mixed>
   *   The decoded JSON response as an associative array.
   */
  private function requestObject(string $method, string $url, array $payload = []): array {
    $raw = $this->executeRequest($method, $url, $payload);

    if (!is_array($raw)) {
      return [];
    }

    /** @var array<string, mixed> $raw */
    return $raw;
  }

  /**
   * Executes an HTTP request and returns the decoded response as a list.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $url
   *   The full request URL.
   * @param array<string, mixed> $payload
   *   The request payload for write operations.
   *
   * @throws \Drupal\mvault\Exception\MvaultNotFoundException
   *   When the API returns 404.
   * @throws \Drupal\mvault\Exception\MvaultApiException
   *   When the API returns any other error status code.
   * @throws \JsonException
   *
   * @return array<int, array<string, mixed>>
   *   The decoded JSON response as a list of associative arrays.
   */
  private function requestList(string $method, string $url, array $payload = []): array {
    $raw = $this->executeRequest($method, $url, $payload);

    if (!is_array($raw)) {
      return [];
    }

    /** @var array<int, array<string, mixed>> $raw */
    return array_values($raw);
  }

  /**
   * Performs the HTTP request and returns the decoded response body.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $url
   *   The full request URL.
   * @param array<string, mixed> $payload
   *   The request payload for write operations.
   *
   * @throws \Drupal\mvault\Exception\MvaultNotFoundException
   *   When the API returns a 404 status code.
   * @throws \Drupal\mvault\Exception\MvaultApiException
   *   When the API returns any other error status code.
   * @throws \JsonException
   *
   * @return mixed
   *   The decoded JSON response body.
   */
  private function executeRequest(string $method, string $url, array $payload = []): mixed {
    $options = [
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Basic ' . $this->buildBasicAuthToken(),
      ],
    ];

    if (!empty($payload)) {
      $options['json'] = $payload;
    }

    try {
      $response = $this->httpClient->request($method, $url, $options);
      $body = $response->getBody()->getContents();

      if ($body === '' || $body === 'null') {
        return [];
      }

      return json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
    } catch (ClientException $e) {
      $statusCode = $e->getResponse()->getStatusCode();
      $responseBody = $e->getResponse()->getBody()->getContents();

      if ($statusCode === 404) {
        throw new MvaultNotFoundException(
          message: sprintf('MVault resource not found at %s', $url),
          code: 404,
          previous: $e,
        );
      }

      $this->logger->error('MVault API error @code at @url: @body', [
        '@code' => $statusCode,
        '@url' => $url,
        '@body' => $responseBody,
      ]);

      throw new MvaultApiException(
        message: sprintf('MVault API returned HTTP %d', $statusCode),
        statusCode: $statusCode,
        responseBody: $responseBody,
        previous: $e,
      );
    } catch (GuzzleException $e) {
      $this->logger->error('MVault HTTP request failed for @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);

      throw new MvaultApiException(
        message: sprintf('MVault HTTP request failed: %s', $e->getMessage()),
        statusCode: 0,
        responseBody: '',
        previous: $e,
      );
    }
  }

  /**
   * Builds the Basic Auth token from the configured credentials.
   *
   * Attempts to retrieve credentials from the Key module first. Falls back to
   * the plain-text `api_key` config value when the Key module is unavailable.
   * The credential value is expected in "api_key:api_secret" format.
   *
   * @return string|null
   *   The Base64-encoded Basic Auth token, or an empty string when no
   *   credentials are configured.
   */
  private function buildBasicAuthToken(): ?string {
    $credentials = $this->resolveCredentials();

    if ($credentials === '') {
      $this->logger->warning(
        'MVault: no API credentials configured. Requests will be unauthenticated.',
      );

      return NULL;
    }

    return base64_encode($credentials);
  }

  /**
   * Resolves the raw credentials string from the configured source.
   *
   * @return string
   *   The raw credential string (e.g. "api_key:api_secret"), or empty string
   *   when no credentials are available.
   */
  private function resolveCredentials(): string {
    $settings = $this->configFactory->get('mvault.settings');
    $keyId = (string) ($settings->get('api_key_id') ?? '');

    if ($this->keyRepository !== NULL && $keyId !== '') {
      $key = $this->keyRepository->getKey($keyId);

      if ($key !== NULL) {
        return (string) $key->getKeyValue();
      }

      $this->logger->warning(
        'MVault API key "@id" not found in Key repository.',
        ['@id' => $keyId],
      );
    }

    return (string) ($settings->get('api_key') ?? '');
  }

  /**
   * Builds the base URL for the MVault API.
   *
   * Priority: settings.php override > module config > default constant.
   *
   * @return string
   *   The base URL without a trailing slash.
   */
  private function baseUrl(): string {
    $override = Settings::get('mvault.base_url');

    if ($override !== NULL) {
      return rtrim((string) $override, '/');
    }

    $configUrl = $this->configFactory->get('mvault.settings')->get('base_url');

    if ($configUrl !== NULL) {
      return rtrim((string) $configUrl, '/');
    }

    return self::DEFAULT_BASE_URL;
  }

  /**
   * Returns the station ID from module configuration.
   *
   * @return string
   *   The configured station ID.
   */
  private function stationId(): string {
    return (string) ($this->configFactory
      ->get('mvault.settings')
      ->get('station_id') ?? '');
  }

  /**
   * Builds the URL for a specific membership by ID.
   *
   * @param string $membershipId
   *   The membership identifier.
   *
   * @return string
   *   The full URL for the membership endpoint.
   */
  private function membershipUrl(string $membershipId): string {
    return sprintf(
      '%s/%s/memberships/%s/',
      $this->baseUrl(),
      $this->stationId(),
      $membershipId,
    );
  }

  /**
   * Builds the URL for email-based membership lookup.
   *
   * @param string $email
   *   The email address to look up.
   *
   * @return string
   *   The full URL for the email lookup endpoint.
   */
  private function emailLookupUrl(string $email): string {
    return sprintf(
      '%s/%s/memberships/filter/email/%s/',
      $this->baseUrl(),
      $this->stationId(),
      urlencode($email),
    );
  }

  /**
   * Builds the URL for active membership lookup by email.
   *
   * @param string $email
   *   The email address to look up.
   *
   * @return string
   *   The full URL for the active membership lookup endpoint.
   */
  private function activeEmailLookupUrl(string $email): string {
    return sprintf(
      '%s/%s/memberships/active/?email=%s',
      $this->baseUrl(),
      $this->stationId(),
      urlencode($email),
    );
  }
}
