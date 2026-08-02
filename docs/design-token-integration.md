# Design token integration

Related: [Emulsify Core 4 and Vite workflow](core-4-vite-workflow.md), [Asset loading](asset-loading.md).

Whisk generates no project asset source tree, Sass entrypoints, WordPress asset registrations, or design-token pipeline. The component library a project selects owns those decisions, and design tokens are optional and project-owned.

Keep token dependencies, configuration, and generated files in the generated child theme so teams that do not use design tokens are not required to install unused tooling. The parent theme has no token behavior. It discovers and enqueues built output under `dist/global` and `dist/components` and nothing else.

Every path below is an illustration. Adapt them to the component library the project installs.

## What the starter ships

- `whisk/src/components/.gitkeep` is a placeholder only. Whisk ships no `tokens.scss`, `foundation.scss`, or `layout.scss` entrypoints and no component tree.
- `whisk/config/.env.example` declares Figma Token Engine credentials. The next section describes it.
- `whisk/config/emulsify-core/` holds shared tooling configuration for ESLint, Stylelint, Prettier, Storybook, the accessibility runner, and Vite extension examples. Treat it as shared tooling rather than project code. Do not put project token configuration there.
- `whisk/package.json` depends on `@emulsify/core` and nothing else. Emulsify Core 4 does not ship Style Dictionary, a token format, or token transforms, so tokens reach the build as ordinary Sass.

The one Core-defined project hook inside that shared directory is `config/emulsify-core/vite/plugins.(mjs|js|cjs)`, created by renaming the shipped `example.plugins.js`. Use it only when a project genuinely needs to add Vite plugins or patch Core's assembled Vite config. Token generation does not need it.

## Figma Token Engine credentials

`whisk/config/.env.example` contains exactly two empty variables and their documentation comments:

```sh
# Added by Figma Token Engine
FIGMA_PERSONAL_ACCESS_TOKEN="" # Your personal Figma Personal Access token https://www.figma.com/developers/api#access-tokens
FIGMA_FILE_URL="" # URL of the Figma file with the tokens
```

The file is a committed template. Nothing in Whisk reads it, and no Whisk npm script runs a token pipeline. It exists so a project that adopts Figma Token Engine already knows which credentials the tooling expects.

Figma Token Engine loads `.env` from the directory you run it in, so copy the template to a `.env` at the generated child theme root, or export the same variables from your shell or CI secret store. `.env` is git-ignored anywhere in the generated theme; `config/.env.example` stays committed. Delete the example if the project will never pull tokens from Figma.

## The Figma Token Engine flow

Figma Token Engine is a published npm package (`figma-token-engine`) that wraps Style Dictionary. The flow is:

1. Tokens live in Figma as published Figma Styles, as Tokens Studio sets, or as a `variables2json` plugin export.
2. The engine authenticates with `FIGMA_PERSONAL_ACCESS_TOKEN` and reads the file at `FIGMA_FILE_URL` through the Figma API. The `variables2json` path reads exported JSON files instead of calling the API.
3. The engine writes the raw token data to the `inputFile` declared in `.tokens.config.json`.
4. Style Dictionary transforms that data and writes the enabled platform outputs into `outputDir`, using fixed filenames such as `tokens.scss`, `tokensMap.scss`, and `tokens.css`.
5. Project Sass loads the generated file, and Emulsify Core's Vite build compiles it with the rest of the theme's Sass.

Install and initialize in the generated child theme:

```bash
npm install --save-dev figma-token-engine
npx figma-token-engine --init
```

`--init` writes `.tokens.config.json` and `.env`. Whisk already supplies the `.env` template, so fill in real values there and keep them out of version control.

A project-owned `.tokens.config.json` looks like this:

```json
{
  "tokenFormat": "FigmaStyles",
  "inputFile": "./config/tokens/figma-tokens.json",
  "outputDir": "./config/tokens/generated",
  "platforms": ["scss", "scssMap"]
}
```

Fetch and transform tokens with `npx figma-token-engine`. Pass `--sd-config-file` when the project needs to merge its own Style Dictionary configuration for extra platforms, transforms, or filters.

## Style Dictionary without Figma

Projects whose token source is committed JSON can run Style Dictionary directly and skip the Figma step:

```bash
npm install --save-dev style-dictionary
```

Add a project-owned config such as `config/tokens/style-dictionary.config.mjs`:

```js
export default {
  source: ['config/tokens/**/*.tokens.json'],
  platforms: {
    scss: {
      transformGroup: 'scss',
      buildPath: 'src/tokens/',
      files: [
        {
          destination: '_tokens.generated.scss',
          format: 'scss/variables',
        },
      ],
    },
  },
};
```

Build it with `npx style-dictionary build --config config/tokens/style-dictionary.config.mjs`. Running Style Dictionary directly lets you choose the destination filename, which matters for the next section.

## Where token source and generated Sass live

Two rules in Emulsify Core's Vite entry model decide what a token file becomes:

- Any `.scss` file in a source root whose filename does not start with `_`, `cl-`, or `sb-` becomes its own compiled entry. Global entries land in `dist/global` and component entries land in `dist/components`. The parent theme enqueues the CSS it finds under `dist/global`, so a compiled token stylesheet becomes an extra stylesheet on every page.
- Non-code files inside the source roots are copied into `dist/` beside the built output. Raw token JSON left under `src/` is copied into `dist/global` and published in the theme directory.

Practical guidance:

- Keep raw and intermediate token JSON outside the source roots, for example in `config/tokens/`. It is build input, not a source asset.
- Figma Token Engine writes fixed filenames, so pointing its `outputDir` inside `src/` produces a compiled and enqueued token stylesheet. Point `outputDir` at a directory outside the source roots and load the generated file by relative path when you do not want that.
- Style Dictionary run directly can write a Sass partial such as `_tokens.generated.scss`. A partial never becomes its own entry, so tokens compile into the stylesheets that consume them.
- Emit a non-partial file on purpose only when the project wants a standalone token stylesheet, for example to publish custom properties for other plugins or for editor styles.

Load the generated Sass from the project-owned entrypoint that needs it:

```scss
@use "../../config/tokens/generated/tokens";
```

The relative path depends on where the selected component library puts its entrypoints.

An example layout, not a required one:

```text
config/tokens/figma-tokens.json        raw token data fetched from Figma
config/tokens/generated/tokens.scss    generated Sass variables
src/tokens/_tokens.generated.scss      generated Sass partial when Style Dictionary runs directly
```

## How tokens reach the Vite build

`npm run build` calls Emulsify Core's Vite config directly, `npm run vite` runs the same config in watch mode, and `npm run develop` runs the watcher alongside Storybook. None of them generate tokens. Emulsify Core exposes no token build script and Whisk does not add one, so token generation is a separate project-owned step that has to finish before Vite reads the Sass:

```bash
npx figma-token-engine
npm run build
```

Choose how the project runs that step: manually when the design source changes, in CI before the build, or through a script the project adds to its own `package.json`. Keep the step in the generated child theme. Do not add it to the parent theme or to `config/emulsify-core/`.

During `npm run vite`, the watcher rebuilds when a file the entrypoints load changes, including generated Sass stored outside `src/`. Storybook loads built CSS by default, so keep the watcher running or rebuild after regenerating tokens if you expect stories to show new values.

## theme.json and design tokens

`theme.json` is WordPress editor configuration. It defines presets, settings, and global styles for the block editor and site editor. It is not a token pipeline and not a replacement for Sass tokens: Emulsify Core's Vite build never reads it, and nothing in the parent theme derives Sass from it.

The parent theme provides the reusable `theme.json` baseline. Whisk ships no child `theme.json` by default; add one only when the project needs its own presets, settings, styles, templates, or style variations.

A project may choose to mirror a subset of token values into `theme.json` presets so the block editor offers the same palette and type scale as the component library. This is optional and project-owned:

```json
{
  "version": 2,
  "settings": {
    "color": {
      "palette": [
        {
          "slug": "brand-primary-base",
          "name": "Brand primary base",
          "color": "#005F89"
        }
      ]
    },
    "typography": {
      "fontSizes": [{ "slug": "3", "name": "3", "size": "16px" }]
    }
  }
}
```

WordPress emits preset custom properties such as `--wp--preset--color--brand-primary-base` from those entries, so the Sass tokens and the editor presets become two representations of the same values.

If you mirror values, treat the token source as canonical, mirror only what editors pick in the interface rather than the whole token set, and re-check the mirror whenever tokens change. Nothing in Emulsify Core or the parent theme synchronizes the two surfaces. Generating a `theme.json` fragment from a Style Dictionary custom format is a reasonable project decision, not a Core feature.

Keep build and Twig configuration out of `theme.json`. Component structure and Twig namespaces belong in `project.emulsify.json`.

## Adopting or opting out

Opting out requires no action:

- No token dependency is installed and no Whisk script expects one.
- Leave `config/.env.example` unused, or delete it if the project will never pull tokens from Figma.
- The shared lint, format, test, and build defaults tolerate a project with no token files and no `src` files.

When a project adopts tokens:

- Install the tooling in the generated child theme, never in the parent theme.
- Keep project token configuration in project-owned locations such as `config/tokens/` and `.tokens.config.json`, not in `config/emulsify-core/`.
- Decide whether generated Sass is committed or produced in CI before the build, and record that decision in the project's `docs/development.md`. CI often has no Figma credentials, which usually argues for committing generated Sass.
- Use the token source format, transforms, and output targets that match the design-system workflow the project already runs.
