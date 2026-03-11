<?php

declare(strict_types=1);

namespace Drupal\mvault\Exception;

/**
 * Exception for HTTP/API errors from the MVault API.
 */
class MvaultApiException extends MvaultException {
  /**
   * Constructs a MvaultApiException.
   *
   * @param string $message
   *   The exception message.
   * @param int $statusCode
   *   The HTTP status code returned by the API.
   * @param string $responseBody
   *   The raw response body from the API.
   * @param int $code
   *   The internal exception code.
   * @param \Throwable|null $previous
   *   The previous exception for chaining.
   */
  public function __construct(
    string $message,
    private readonly int $statusCode,
    private readonly string $responseBody = '',
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, $code, $previous);
  }

  /**
   * Returns the HTTP status code from the API response.
   *
   * @return int
   *   The HTTP status code.
   */
  public function getStatusCode(): int {
    return $this->statusCode;
  }

  /**
   * Returns the raw response body from the API.
   *
   * @return string
   *   The response body.
   */
  public function getResponseBody(): string {
    return $this->responseBody;
  }
}
