# Drupal Plugin Configuration Forms with SubformState

## When to use this

Use this pattern when your entity form includes a plugin selector (e.g., verification plugin, field type plugin) and the selected plugin has its own configuration form that implements `PluginFormInterface`.

## The pattern

### 1. Build Phase (`form()` method)

Create the plugin instance with existing configuration and build its subform:

```php
$selectedPlugin = $this->resolveSelectedPlugin($form_state);

if ($selectedPlugin !== NULL && $selectedPlugin !== '') {
  $existingConfig = $entity->getPluginConfig();
  $plugin = $this->pluginManager->createInstance($selectedPlugin, $existingConfig);

  if ($plugin instanceof PluginFormInterface) {
    $subform = ['#parents' => ['plugin_config']];
    $form['plugin_config'] += $plugin->buildConfigurationForm(
      $subform,
      SubformState::createForSubform($subform, $form, $form_state),
    );
  }
}
```

### 2. Validate Phase (`validateForm()` method)

Create the plugin with form state values and run its validation:

```php
$config = $this->resolvePluginConfig($form_state);
$plugin = $this->pluginManager->createInstance($selectedPlugin, $config);

if ($plugin instanceof PluginFormInterface) {
  $subform = ['#parents' => ['plugin_config']];
  $plugin->validateConfigurationForm(
    $form['wrapper']['plugin_config'],
    SubformState::createForSubform($subform, $form, $form_state),
  );
}
```

### 3. Submit Phase (`submitForm()` method)

**Critical:** Process the plugin in `submitForm()` BEFORE `parent::submitForm()`, not in `save()`:

```php
public function submitForm(array &$form, FormStateInterface $form_state): void {
  $this->submitPluginConfigurationForm($form, $form_state);
  parent::submitForm($form, $form_state);
}

protected function submitPluginConfigurationForm(array &$form, FormStateInterface $form_state): void {
  $selectedPlugin = $this->resolveSelectedPlugin($form_state);

  if ($selectedPlugin === NULL || $selectedPlugin === '') {
    $form_state->setValue('plugin_config', []);
    return;
  }

  $config = $this->resolvePluginConfig($form_state);
  $plugin = $this->pluginManager->createInstance($selectedPlugin, $config);

  if ($plugin instanceof PluginFormInterface) {
    $subform = ['#parents' => ['plugin_config']];
    $plugin->submitConfigurationForm(
      $subform,
      SubformState::createForSubform($subform, $form, $form_state),
    );

    // Update form state with processed config
    $form_state->setValue(['plugin_config'], $plugin->getConfiguration());
  }
}
```

## Critical gotchas

### 1. `#parents` must match actual form value paths

If your container element (e.g., `details`) doesn't have `#tree => TRUE`, values are flattened to the parent level:

```php
// WRONG - if 'verification' details has no #tree
$subform = ['#parents' => ['verification', 'plugin_config']];

// CORRECT - values are at top level
$subform = ['#parents' => ['plugin_config']];
```

### 2. Submit in `submitForm()`, not `save()`

The entity is built from form state values after `parent::submitForm()` is called. If you process the plugin configuration in `save()`, the updated values won't be included when the entity is built.

### 3. Check for empty string AND null

Select elements with `#empty_value => ''` return empty string when "- None -" is selected, not null:

```php
// WRONG - empty string passes this check
if ($selectedPlugin !== NULL) { ... }

// CORRECT
if ($selectedPlugin !== NULL && $selectedPlugin !== '') { ... }
```

### 4. Use the correct interface for `getConfiguration()`

`PluginFormInterface` doesn't include `getConfiguration()`. You need `ConfigurableInterface` or your plugin's specific interface:

```php
// WRONG - PluginFormInterface has no getConfiguration()
if ($plugin instanceof PluginFormInterface) {
  $plugin->getConfiguration(); // PHPStan error
}

// CORRECT - use interface that extends ConfigurableInterface
if ($plugin instanceof MyPluginInterface) {
  $plugin->getConfiguration(); // Works
}
```

## See also

- `src/Form/WebhookSourceTypeForm.php` - Implementation example
- `docs/patterns/drupal-ajax-form-values.md` - Related AJAX form state handling
