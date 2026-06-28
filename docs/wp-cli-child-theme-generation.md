# WP-CLI child theme generation

The parent theme includes a WP-CLI command for generating a project child theme from the bundled Whisk starter.

## Basic usage

```sh
wp emulsify "Acme Site" --dry-run
wp emulsify "Acme Site" --machine-name=acme-site
wp emulsify "Acme Site" --machine-name=acme-site --force
wp emulsify "Acme Site" --machine-name=acme-site --activate
```

## Options

| Option | Purpose |
| --- | --- |
| `--machine-name=<slug>` | Override the generated slug used for paths and package metadata. |
| `--dry-run` | Show what would be created or changed without writing files. |
| `--force` | Replace an existing destination. Use this only when replacement is intentional. |
| `--activate` | Activate the generated child theme after creation. |

## What the generator updates

The generator copies `emulsify/whisk` to a sibling child theme directory and updates:

- WordPress `Theme Name`.
- WordPress `Text Domain`.
- WordPress `Template`.
- `package.json` name.
- `project.emulsify.json` project name.
- `project.emulsify.json` machine name.
- Visible Whisk starter labels where appropriate.
- Lowercase slug references where appropriate.

The generator avoids broad blind string replacement. It targets known metadata files and starter labels.

Ignored dependency, cache, Storybook, and Vite output directories are not copied. A generated child theme should install its own dependencies, install or configure the chosen Emulsify component system, and create its own `dist` output.

## Emulsify CLI starter hook

Emulsify CLI uses the standalone starter repository at `https://github.com/emulsify-ds/emulsify-wordpress-starter`. That repository should be built from this branch's `whisk/` directory, which is the generated child theme layer rather than the parent runtime theme root.

The starter includes `.cli/init.js` for CLI initialization. Current Emulsify CLI timing is:

1. Clone the starter.
2. Write `project.emulsify.json`.
3. Run `npm install`.
4. Execute `.cli/init.js`.

Because `npm install` runs before the hook, the hook updates package metadata and the generated `package-lock.json` when the lockfile exists. A pre-install CLI hook is not required for the current targeted metadata updates.

The hook deliberately mirrors the parent WP-CLI generator's targeted updates:

- `style.css` `Theme Name`, `Text Domain`, and `Template`.
- `package.json` name and generated package lockfile root name.
- `project.emulsify.json` project name and machine name, while keeping `"platform": "wordpress"`.
- Visible Whisk labels in known starter files.
- `templates/page.twig` class from `whisk-page` to the generated machine-name class.
- JSON pattern names from the `whisk/*` namespace to the generated machine-name namespace.

The generated child theme must keep `Template: emulsify`; the parent theme remains installed separately as `emulsify`.

## After generation

Install dependencies and build assets in the generated child theme:

```sh
cd web/app/themes/acme-site
npm install
npm run build
```

Activate the child theme for site work. The parent theme should stay installed as `emulsify`.
