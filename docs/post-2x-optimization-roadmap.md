# Post-2.x optimization roadmap

Emulsify WordPress 2.0 should stay focused on the parent and generated child theme release contract. The items below are follow-up opportunities for focused minor releases, not 2.0 blockers.

## Recommended implementation order

1. Ship 2.0 without adding new runtime features.
2. Build on Composer PSR-4 autoloading with grouped runtime directories in a minor release.
3. Add optional manifest-driven asset loading.
4. Add CLI diagnostics such as `wp emulsify doctor`.
5. Expand diagnostics around optional persistent discovery caching once projects have real-world invalidation guidance feedback.

## Code organization

- Move runtime PHP classes into grouped directories such as setup, assets, blocks, editor, and CLI while preserving public hooks and filters.
- Move current legacy class filenames toward clean PSR-4 paths in a minor release.
- Keep compatibility shims or clear upgrade notes for any class-loading paths that existing child themes may reference.

## Runtime architecture

- Split bootstrap responsibilities into small provider-style classes so setup, Twig, assets, block registration, and editor policy can be reasoned about independently.
- Keep the parent theme focused on reusable WordPress runtime behavior; project behavior should remain in child themes or site plugins.
- Document service boundaries before adding new runtime services.

## Asset loading and performance

- Add optional manifest-driven asset loading for `dist/global` and component assets.
- Keep current directory scanning as the fallback while manifests are optional.
- Avoid requiring build output in fresh Whisk child themes until a project installs a component system.

## Component/block discovery

- Refine child-first discovery diagnostics for duplicate ACF/Twig and native block definitions.
- Keep optional persistent discovery caching production-enabled but filter-controlled; future work should focus on diagnostics and invalidation guidance, not UI.
- Keep discovery safe when ACF or native block APIs are unavailable.

## CLI diagnostics

- Add a read-only `wp emulsify doctor` command that reports parent install state, active child theme, `Template` header, Timber availability, generated metadata, and missing build output.
- Use diagnostics before adding repair commands.
- Keep generation behavior stable so the 2.0 WP-CLI contract remains predictable.

## Editor and block feature modules

- Expand editor enhancements as opt-in modules, not broad parent-theme defaults.
- Treat ACF/Twig blocks, native Gutenberg blocks, core block Twig rendering, and pattern policies as independently enableable areas.
- Add documentation and smoke coverage with each module.

## Documentation and support tooling

- Maintain upgrade checklists, troubleshooting notes, and component recipes as follow-up support material.
- Surface generated child theme lineage metadata in diagnostics and support docs.
- Keep examples as recipes instead of active starter files in `whisk/src/components`.
