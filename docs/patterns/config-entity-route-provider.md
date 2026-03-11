# Config Entity Route Provider Gotcha

## When this applies

When creating config entities with admin UI (list pages, add/edit forms) and menu links defined in `*.links.menu.yml`.

## The pattern

Config entities must include a `route_provider` handler in the `#[ConfigEntityType]` attribute. Without it, routes like `entity.{entity_type}.collection` are never generated, even though the `links` array defines the paths.

**Symptom:** Menu items silently fail to appear — no error, just missing UI.

**Fix:** Add `AdminHtmlRouteProvider` to the handlers array:

```php
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

#[ConfigEntityType(
  id: 'my_config_entity',
  // ...
  handlers: [
    'list_builder' => MyListBuilder::class,
    'form' => [
      'add' => MyForm::class,
      'edit' => MyForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    // THIS IS REQUIRED for menu links to work:
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'collection' => '/admin/config/services/my-entity',
    'add-form' => '/admin/config/services/my-entity/add',
    'edit-form' => '/admin/config/services/my-entity/{my_config_entity}/edit',
    'delete-form' => '/admin/config/services/my-entity/{my_config_entity}/delete',
  ],
)]
```

## Debugging

Check if routes exist:

```bash
ddev drush route:debug | grep my_config_entity
```

If no routes appear, the route_provider handler is missing.

## See also

- Drupal core `Workflow` entity for reference implementation
