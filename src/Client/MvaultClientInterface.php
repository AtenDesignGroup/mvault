<?php

declare(strict_types=1);

namespace Drupal\mvault\Client;

use Drupal\mvault\ValueObject\Membership;

/**
 * Interface for interacting with the PBS MVault API.
 */
interface MvaultClientInterface {

  /**
   * Creates a new membership in MVault.
   *
   * @param string $membershipId
   *   The unique membership identifier to assign (placed in the URL, not
   *   payload).
   * @param \Drupal\mvault\ValueObject\Membership $membership
   *   The membership data to create.
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   The created membership as returned by the API.
   * @throws \Drupal\mvault\Exception\MvaultApiException
   *   When the API returns a non-successful HTTP status code.
   * @throws \Drupal\mvault\Exception\MvaultNotFoundException
   */
  public function createMembership(string $membershipId, Membership $membership): Membership;

  /**
   * Retrieves a membership by email address.
   *
   * Returns null when no membership is found (404 response).
   *
   * @param string $email
   *   The member's email address.
   *
   * @return \Drupal\mvault\ValueObject\Membership|null
   *   The membership, or null if not found.
   * @throws \Drupal\mvault\Exception\MvaultApiException
   *   When the API returns an unexpected error status code.
   * @throws \JsonException
   */
  public function getMembershipByEmail(string $email): ?Membership;

  /**
   * Retrieves the active membership for an email address.
   *
   * Returns null when no active membership exists.
   *
   * @param string $email
   *   The member's email address.
   *
   * @return \Drupal\mvault\ValueObject\Membership|null
   *   The active membership, or null if none exists.
   * @throws \Drupal\mvault\Exception\MvaultApiException
   *   When the API returns an unexpected error status code.
   *
   * @throws \JsonException
   */
  public function getActiveMembershipByEmail(string $email): ?Membership;

  /**
   * Renews an existing membership with a new expiration date.
   *
   * @param string $membershipId
   *   The unique membership identifier to renew.
   * @param \DateTimeImmutable $newExpireDate
   *   The new expiration date for the membership.
   * @param \Drupal\mvault\ValueObject\Membership $existingMembership
   *   The existing membership data to update.
   *
   * @return \Drupal\mvault\ValueObject\Membership
   *   The updated membership as returned by the API.
   * @throws \Drupal\mvault\Exception\MvaultApiException
   *   When the API returns an unexpected error status code.
   *
   * @throws \Drupal\mvault\Exception\MvaultNotFoundException
   *   When the membership does not exist (404 response).
   */
  public function renewMembership(string $membershipId, \DateTimeImmutable $newExpireDate, Membership $existingMembership): Membership;

}
