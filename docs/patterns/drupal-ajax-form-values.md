# Drupal AJAX Form Values

## When to use this

When building Drupal forms with AJAX-driven fields that depend on other field values (e.g., a bundle dropdown that updates based on entity type selection).

## The pattern

### Problem: getValue() returns null during AJAX

During AJAX rebuilds, `$form_state->getValue('field_name')` returns `null` because Drupal only populates processed values *after* the form tree is validated and processed — which happens after `buildForm()` is called. To get submitted values during AJAX callbacks, check `$form_state->getUserInput()` first.

### Solution: Reusable resolver method

```php
protected function resolveFormValue(FormStateInterface $form_state, string $key, string $default = ''): string {
  $input = $form_state->getUserInput();
  if (!empty($input[$key])) {
    return $input[$key];
  }

  $value = $form_state->getValue($key);
  if (!empty($value)) {
    return $value;
  }

  return $default;
}
```

**Usage:**
```php
$selectedEntityType = $this->resolveFormValue($form_state, 'target_entity_type', $entity->getTargetEntityTypeId());
```

**Caveat:** `getUserInput()` returns raw, unvalidated data. Only use it for rebuilding form elements, not for saving to the database.

## AJAX select fields with dynamic options

### Problem: Validation fails with "submitted value is not allowed"

Drupal's Form API validates submitted select values against the `#options` array. For optional fields with dynamic options, you must configure empty value handling.

### Solution: Use #empty_option and hide until ready

```php
$selectedEntityType = $this->resolveFormValue($form_state, 'target_entity_type', $entity->getTargetEntityTypeId());
$bundleOptions = $this->getBundleOptions($selectedEntityType);

$form['target_entity_bundle'] = [
  '#type' => 'select',
  '#title' => $this->t('Target Bundle'),
  '#options' => $bundleOptions,
  '#default_value' => $entity->getTargetEntityBundle(),
  '#empty_option' => $this->t('- Any -'),
  '#access' => !empty($selectedEntityType),
  '#prefix' => '<div id="bundle-wrapper">',
  '#suffix' => '</div>',
];
```

**Key points:**
- `#empty_option` — Renders a placeholder and allows empty submission
- `#access` — Hide the field until the parent value is set

## See also

- [Drupal Form API AJAX documentation](https://www.drupal.org/docs/drupal-apis/form-api/ajax-forms)
