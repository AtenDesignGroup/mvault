<?php

declare(strict_types=1);

namespace Drupal\mvault\Exception;

/**
 * Exception thrown when a membership is not found (HTTP 404 on update operations).
 */
class MvaultNotFoundException extends MvaultException {
}
