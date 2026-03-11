<?php

declare(strict_types=1);

namespace Drupal\Tests\mvault\Unit\ValueObject;

use Drupal\mvault\ValueObject\Membership;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the Membership value object.
 *
 * @coversDefaultClass \Drupal\mvault\ValueObject\Membership
 * @group mvault
 */
class MembershipTest extends UnitTestCase {

  /**
   * A fully-populated API response fixture.
   *
   * @var array<string, mixed>
   */
  private const FULL_API_RESPONSE = [
    'membership_id' => 'en_12345',
    'first_name' => 'Jane',
    'last_name' => 'Doe',
    'email' => 'jane@example.com',
    'offer' => 'PASSPORT_MONTHLY',
    'start_date' => '2025-01-01T00:00:00Z',
    'expire_date' => '2026-01-01T00:00:00Z',
    'status' => 'On',
    'token' => 'abc-token-xyz',
    'additional_metadata' => ['library_id' => 'LIB-001'],
  ];

  // ---------------------------------------------------------------------------
  // fromApiResponse() tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseMapsFirstName(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('Jane', $membership->firstName);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseMapsLastName(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('Doe', $membership->lastName);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseMapsEmail(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('jane@example.com', $membership->email);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseMapsOffer(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('PASSPORT_MONTHLY', $membership->offer);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseMapsMembershipId(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('en_12345', $membership->membershipId);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseMapsStatus(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('On', $membership->status);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseMapsToken(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('abc-token-xyz', $membership->token);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseMapsAdditionalMetadata(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame(['library_id' => 'LIB-001'], $membership->additionalMetadata);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseParsesStartDateValue(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('2025-01-01', $membership->startDate->format('Y-m-d'));
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseParsesExpireDateValue(): void {
    $membership = Membership::fromApiResponse(self::FULL_API_RESPONSE);

    $this->assertSame('2026-01-01', $membership->expireDate->format('Y-m-d'));
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseSetsNullMembershipIdWhenAbsent(): void {
    $data = self::FULL_API_RESPONSE;
    unset($data['membership_id']);

    $membership = Membership::fromApiResponse($data);

    $this->assertNull($membership->membershipId);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseSetsNullTokenWhenAbsent(): void {
    $data = self::FULL_API_RESPONSE;
    unset($data['token']);

    $membership = Membership::fromApiResponse($data);

    $this->assertNull($membership->token);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseSetsNullAdditionalMetadataWhenAbsent(): void {
    $data = self::FULL_API_RESPONSE;
    unset($data['additional_metadata']);

    $membership = Membership::fromApiResponse($data);

    $this->assertNull($membership->additionalMetadata);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseDefaultsStatusToOnWhenAbsent(): void {
    $data = self::FULL_API_RESPONSE;
    unset($data['status']);

    $membership = Membership::fromApiResponse($data);

    $this->assertSame('On', $membership->status);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseDefaultsStartDateToEpochWhenAbsent(): void {
    $data = self::FULL_API_RESPONSE;
    unset($data['start_date']);

    $membership = Membership::fromApiResponse($data);

    $this->assertSame('1970-01-01', $membership->startDate->format('Y-m-d'));
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseDefaultsExpireDateToEpochWhenAbsent(): void {
    $data = self::FULL_API_RESPONSE;
    unset($data['expire_date']);

    $membership = Membership::fromApiResponse($data);

    $this->assertSame('1970-01-01', $membership->expireDate->format('Y-m-d'));
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseHandlesEmptyArray(): void {
    $membership = Membership::fromApiResponse([]);

    $this->assertSame('', $membership->firstName);
    $this->assertSame('', $membership->lastName);
    $this->assertSame('', $membership->email);
    $this->assertSame('', $membership->offer);
    $this->assertNull($membership->membershipId);
    $this->assertNull($membership->token);
    $this->assertNull($membership->additionalMetadata);
  }

  /**
   * @covers ::fromApiResponse
   */
  public function testFromApiResponseIgnoresNonArrayAdditionalMetadata(): void {
    $data = self::FULL_API_RESPONSE;
    $data['additional_metadata'] = 'not-an-array';

    $membership = Membership::fromApiResponse($data);

    $this->assertNull($membership->additionalMetadata);
  }

  // ---------------------------------------------------------------------------
  // toApiPayload() tests
  // ---------------------------------------------------------------------------

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadContainsFirstName(): void {
    $membership = $this->buildMembership();

    $payload = $membership->toApiPayload();

    $this->assertSame('Jane', $payload['first_name']);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadContainsLastName(): void {
    $membership = $this->buildMembership();

    $payload = $membership->toApiPayload();

    $this->assertSame('Doe', $payload['last_name']);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadContainsEmail(): void {
    $membership = $this->buildMembership();

    $payload = $membership->toApiPayload();

    $this->assertSame('jane@example.com', $payload['email']);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadContainsOffer(): void {
    $membership = $this->buildMembership();

    $payload = $membership->toApiPayload();

    $this->assertSame('PASSPORT_MONTHLY', $payload['offer']);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadContainsStatus(): void {
    $membership = $this->buildMembership();

    $payload = $membership->toApiPayload();

    $this->assertSame('On', $payload['status']);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadExcludesMembershipId(): void {
    $membership = $this->buildMembership(membershipId: 'en_12345');

    $payload = $membership->toApiPayload();

    $this->assertArrayNotHasKey('membership_id', $payload);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadExcludesToken(): void {
    $membership = $this->buildMembership(token: 'abc-token-xyz');

    $payload = $membership->toApiPayload();

    $this->assertArrayNotHasKey('token', $payload);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadExcludesCreateDate(): void {
    $membership = $this->buildMembership();

    $payload = $membership->toApiPayload();

    $this->assertArrayNotHasKey('create_date', $payload);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadFormatsStartDateAsIso8601(): void {
    $startDate = new \DateTimeImmutable('2025-01-15T00:00:00Z');
    $membership = $this->buildMembership(startDate: $startDate);

    $payload = $membership->toApiPayload();

    $this->assertSame('2025-01-15T00:00:00Z', $payload['start_date']);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadFormatsExpireDateAsIso8601(): void {
    $expireDate = new \DateTimeImmutable('2026-01-15T00:00:00Z');
    $membership = $this->buildMembership(expireDate: $expireDate);

    $payload = $membership->toApiPayload();

    $this->assertSame('2026-01-15T00:00:00Z', $payload['expire_date']);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadIncludesAdditionalMetadataWhenPresent(): void {
    $membership = $this->buildMembership(additionalMetadata: ['library_id' => 'LIB-001']);

    $payload = $membership->toApiPayload();

    $this->assertSame(['library_id' => 'LIB-001'], $payload['additional_metadata']);
  }

  /**
   * @covers ::toApiPayload
   */
  public function testToApiPayloadOmitsAdditionalMetadataKeyWhenNull(): void {
    $membership = $this->buildMembership(additionalMetadata: NULL);

    $payload = $membership->toApiPayload();

    $this->assertArrayNotHasKey('additional_metadata', $payload);
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Builds a Membership with sensible defaults and optional overrides.
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
   *   Start date (defaults to 2025-01-01).
   * @param \DateTimeImmutable|null $expireDate
   *   Expire date (defaults to 2026-01-01).
   * @param string|null $membershipId
   *   Membership identifier.
   * @param string|null $status
   *   Membership status.
   * @param string|null $token
   *   Activation token.
   * @param array<string, mixed>|null $additionalMetadata
   *   Additional metadata.
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   The constructed membership.
   */
  private function buildMembership(
    string $firstName = 'Jane',
    string $lastName = 'Doe',
    string $email = 'jane@example.com',
    string $offer = 'PASSPORT_MONTHLY',
    ?\DateTimeImmutable $startDate = NULL,
    ?\DateTimeImmutable $expireDate = NULL,
    ?string $membershipId = NULL,
    ?string $status = 'On',
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
      status: $status,
      token: $token,
      additionalMetadata: $additionalMetadata,
    );
  }

}
