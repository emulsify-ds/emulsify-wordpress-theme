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

Install or author project components in the structure defined by the selected Emulsify component system. For compatible component roots, new project includes can use the generated child theme machine name from `project.emulsify.json`:

```twig
{% include "project_machine_name:example-card" with {
  heading: post.title
} only %}
```

The general form is `project_machine_name:component_name`.

This reference resolves child-first compatibility roots, including `src/components/example-card/example-card.twig`, `src/components/example-card.twig`, `components/example-card/example-card.twig`, and `components/example-card.twig`. One-level grouped names such as `project_machine_name:ui/heading` resolve to paths like `src/components/ui/heading/heading.twig`.

The legacy `@components/example-card/example-card.twig` namespace remains supported for compatible component libraries, existing projects, shared templates, and migration work:

```twig
{% include "@components/example-card/example-card.twig" with {
  heading: post.title
} only %}
```

Use `emulsify_theme_project_component_roots` to adjust roots for `project_machine_name:component_name` references. Use `emulsify_theme_twig_namespaces` for normal `@namespace/path.twig` paths.

Root-level `components` and `src/components` directories are compatibility paths, not starter requirements. Do not remove existing `@components` includes during migration; both forms are supported.

## Attribute helpers

The parent theme registers Core-style helpers for class and attribute handling:

```twig
<div {{ bem('example-card', ['featured']) }}>
  {{ content }}
</div>

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
