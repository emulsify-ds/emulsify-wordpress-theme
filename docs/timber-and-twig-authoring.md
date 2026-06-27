# Timber and Twig authoring

Timber provides the bridge between WordPress data and Twig templates. Emulsify WordPress keeps Twig authoring predictable by registering a small set of namespaces and helper functions from the parent theme.

## Bedrock and Timber

Timber is required for frontend rendering. A standalone theme install can use the parent theme `composer.json`:

```sh
cd web/app/themes/emulsify
composer install
```

In Bedrock, it is also valid to install Timber from the application-level Composer project as long as WordPress loads that Composer autoloader before the theme renders.

If Timber is missing, the parent theme shows an admin notice and stops frontend rendering with a clear runtime error.

## Twig namespaces

The parent theme registers these namespaces:

- `@templates`: child templates first, then parent fallbacks.
- `@emulsify-tpl`: parent templates only.
- `@components`: child component paths first, then parent component paths.

Use `@templates` for normal includes. Use `@emulsify-tpl` when a child override extends the parent template with the same relative path.

```twig
{% extends '@emulsify-tpl/page.twig' %}

{% block content %}
  <div class="project-page">
    {{ parent() }}
  </div>
{% endblock %}
```

## Component includes

Author project components under `src/components` in the generated child theme:

```twig
{% include '@components/button/button.twig' with {
  text: post.title
} only %}
```

Root-level `components` directories remain a compatibility path for older projects. New work should use `src/components`.

## Attribute helpers

The parent theme registers Core-style helpers for class and attribute handling:

```twig
<button {{ bem('button', ['primary']) }}>
  {{ text }}
</button>

<div {{ add_attributes({ class: ['foo'], 'data-component': 'example' }) }}>
  {{ content }}
</div>
```

The helpers return an attribute bag that serializes safely in Twig string contexts.

## Global context

The parent theme adds common values such as `site`, `theme`, `menu`, `post`, `wp`, and `body_class` to the Timber context. Child themes and project plugins can add values with:

```php
add_filter(
  'emulsify_theme_context',
  function ( array $context ): array {
    $context['project'] = array(
      'name' => 'Acme Site',
    );

    return $context;
  }
);
```
