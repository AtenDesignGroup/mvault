<?php

declare(strict_types=1);

namespace Drupal\mvault\ValueObject;

/**
 * Value object representing a PBS MVault membership.
 */
final readonly class Membership {

  /**
   * Constructs a Membership value object.
   *
   * @param string $firstName
   *   The member's first name.
   * @param string $lastName
   *   The member's last name.
   * @param string $email
   *   The member's email address.
   * @param string $offer
   *   The offer/membership tier identifier.
   * @param \DateTimeImmutable $startDate
   *   The membership start date.
   * @param \DateTimeImmutable $expireDate
   *   The membership expiration date.
   * @param string|null $membershipId
   *   The unique membership identifier (read-only from API after creation).
   * @param string|null $status
   *   The membership status (e.g., 'On', 'Off').
   * @param string|null $token
   *   The activation token (read-only, returned by API).
   * @param string|null $additionalMetadata
   *   Additional metadata as a JSON string.
   */
  public function __construct(
    public string $firstName,
    public string $lastName,
    public string $email,
    public string $offer,
    public \DateTimeImmutable $startDate,
    public \DateTimeImmutable $expireDate,
    public ?string $membershipId = NULL,
    public ?string $status = 'On',
    public ?string $token = NULL,
    public ?string $additionalMetadata = NULL,
  ) {}

  /**
   * Creates a Membership instance from an MVault API response array.
   *
   * @param array<string, mixed> $data
   *   The API response data.
   *
   * @return self
   *   A new Membership instance populated from the API response.
   *
   * @throws \DateMalformedStringException
   */
  public static function fromApiResponse(array $data): self {
    return new self(
      firstName: (string) ($data['first_name'] ?? ''),
      lastName: (string) ($data['last_name'] ?? ''),
      email: (string) ($data['email'] ?? ''),
      offer: (string) ($data['offer'] ?? ''),
      startDate: self::parseDateField($data['start_date'] ?? NULL),
      expireDate: self::parseDateField($data['expire_date'] ?? NULL),
      membershipId: isset($data['membership_id']) ? (string) $data['membership_id'] : NULL,
      status: isset($data['status']) ? (string) $data['status'] : 'On',
      token: isset($data['token']) ? (string) $data['token'] : NULL,
      additionalMetadata: isset($data['additional_metadata'])
        ? (string) $data['additional_metadata']
        : NULL,
    );
  }

  /**
   * Converts the membership to an API payload array, excluding read-only
   * fields.
   *
   * Read-only fields excluded: membership_id, token, create_date.
   *
   * @return array<string, mixed>
   *   The payload array suitable for sending to the MVault API.
   */
  public function toApiPayload(): array {
    $payload = [
      'first_name' => $this->firstName,
      'last_name' => $this->lastName,
      'email' => $this->email,
      'offer' => $this->offer,
      'start_date' => $this->startDate->format('Y-m-d\TH:i:s\Z'),
      'expire_date' => $this->expireDate->format('Y-m-d\TH:i:s\Z'),
      'status' => $this->status,
    ];

    if ($this->additionalMetadata !== NULL) {
      $payload['additional_metadata'] = $this->additionalMetadata;
    }

    return $payload;
  }

  /**
   * Parses a date string from the API into a DateTimeImmutable.
   *
   * @param string|null $value
   *   The date string to parse, or null.
   *
   * @return \DateTimeImmutable
   *   A DateTimeImmutable instance, defaulting to epoch if value is null or
   *   invalid.
   * @throws \DateMalformedStringException
   *
   */
  private static function parseDateField(?string $value): \DateTimeImmutable {
    if ($value === NULL || $value === '') {
      return new \DateTimeImmutable('1970-01-01T00:00:00Z');
    }

    $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
    if ($date === FALSE) {
      $date = new \DateTimeImmutable($value);
    }

    return $date;
  }

}
