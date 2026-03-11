# DependencySerializationTrait and Non-Readonly Properties

## When to use this

When writing Drupal form classes that inject services via the constructor **and** use AJAX callbacks. Any form that uses `#ajax` will be serialized into the session and later reconstructed, which is incompatible with readonly properties.

## The problem: readonly breaks AJAX form serialization

PHP's `readonly` modifier prevents a property from being written after it is initialized. Drupal's `DependencySerializationTrait` (used by `FormBase`, `EntityForm`, and other base classes) implements `__sleep()` and `__wakeup()` to handle service serialization safely:

- `__sleep()` records the service ID for each injected service instead of serializing the service object itself.
- `__wakeup()` re-fetches each service from the container by ID and **assigns it back to the property**.

Because `__wakeup()` assigns to the property after construction, the assignment fails with a fatal error if the property is `readonly`.

## Correct pattern for AJAX-capable forms

Declare injected service properties as `protected` (not `readonly`) and assign them in the constructor body:

```php
class WebhookSourceTypeForm extends EntityForm {

  /**
   * The entity field manager service.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The webhook verification plugin manager.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected WebhookVerificationManagerInterface $verificationManager;

  public function __construct(
    RouteMatchInterface $routeMatch,
    EntityFieldManagerInterface $entityFieldManager,
    WebhookVerificationManagerInterface $verificationManager,
  ) {
    $this->routeMatch = $routeMatch;
    $this->entityFieldManager = $entityFieldManager;
    $this->verificationManager = $verificationManager;
  }

}
```

## When readonly IS safe

`readonly` is safe for properties that are not injected services tracked by `DependencySerializationTrait`. In practice, this means:

- Value objects and scalars stored at construction time.
- Services injected in classes that do **not** extend a class using `DependencySerializationTrait` (e.g., plain service classes, event subscribers).
- Non-AJAX form fields that are rebuilt from scratch on each request (no session serialization).

For services in plain service classes (not forms), prefer the modern constructor promotion style:

```php
class WebhookQueueService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerChannelInterface $logger,
  ) {}

}
```

## How DependencySerializationTrait works internally

```
Request 1 (initial page load)
  └── Form object constructed, services injected
  └── Form saved to session: service IDs stored, not objects

Request 2 (AJAX callback)
  └── Form unserialized from session
  └── __wakeup() called
  └── Each service ID looked up in container
  └── Service object assigned back to property  ← fails if readonly
  └── Form rebuild proceeds with live services
```

## See also

- `src/Form/WebhookSourceTypeForm.php` — example of this pattern in this module
- `src/Traits/AjaxFormStateTrait.php` — companion trait for resolving form values during AJAX rebuilds
- [Drupal core DependencySerializationTrait](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21DependencyInjection%21DependencySerializationTrait.php/trait/DependencySerializationTrait)
