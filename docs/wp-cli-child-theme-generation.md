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

## After generation

Install dependencies and build assets in the generated child theme:

```sh
cd web/app/themes/acme-site
npm install
npm run build
```

Activate the child theme for site work. The parent theme should stay installed as `emulsify`.
