# Emulsify WordPress 2.0 — Implementation Prompts

Copiable, self-contained prompts for a coding AI to implement the release fixes from `RELEASE-REVIEW-PR29.md`. Each prompt has a Summary, Goal, grounded Context (real files/lines), Tasks, Acceptance criteria, and Commit/Push instructions.

## Decisions locked in (from the open-questions answers)

- **Distribution:** Composer is the primary/tracked path, **but manual download/install (and eventually wordpress.org) must work** — so the release must publish a ZIP with `vendor/` bundled (Prompt 4). wordpress.org SVN deploy is a later step (profile not yet created); do **not** implement SVN now.
- **Autoescape:** Not intentional — **enable it** (WordPress/Timber best practice). Requires adding `|raw` to known-HTML sinks so rendering doesn't break (Prompt 1).
- **CI skipped jobs:** "WordPress fixture smoke" and "Extended Whisk Storybook and a11y" are gated to skip on PRs and must be made to **run and pass** (Prompt 5). Note the Whisk starter is component-agnostic, so Storybook/a11y needs a seeded component system to pass.
- **Fixture run:** Will be run after these changes land (Prompt 5 makes it a real gate).
- **phpcs/phpstan:** Make it clearly runnable locally and a first-class CI check (Prompt 5).

## Suggested workflow

- Branch each prompt off `release-2.x` (e.g. `fix/twig-autoescape`), implement, run checks, commit with a Conventional Commit message (the repo uses commitlint + semantic-release), push, and open a PR **targeting `release-2.x`** (the 2.0 release branch that PR #29 merges into `main`). You can instead stack them on one branch if you prefer fewer PRs — adjust the branch instructions accordingly.
- Priority order: **Prompt 1 → 2 → 3** (must-fix), then **4 → 5** (your explicit asks), then **6** (cleanup).
- Local prerequisites for the AI: PHP 8.3, Composer, Node 24. Run `npm ci --ignore-scripts && composer install` before PHP checks.

---

## Prompt 1 — Enable Twig autoescaping and fix raw-HTML sinks (Security: S1, S2)

```text
SUMMARY
Emulsify WordPress renders all Timber/Twig output with autoescape OFF (Timber 2's default is never overridden), and it injects ACF field values and Gutenberg block attributes into the Twig context unescaped. This is a stored-XSS enabler for child themes. Enable HTML autoescaping for the theme's Twig environment and fix the parent templates that legitimately output HTML so they use |raw. Also stop preferring an editor-persisted template path.

GOAL
- Twig autoescape is ON ('html') for all theme render paths.
- Parent templates render correctly (no double-escaped content) after the change.
- bem()/add_attributes() output is NOT double-escaped (they are is_safe:html).
- ACF/block Twig rendering no longer trusts a persisted, editor-influenceable template path.
- A behavioral test proves a hostile ACF field value is escaped at render time.

CONTEXT (verified file:line)
- includes/Runtime/Twig.php:26-30 register() adds only loader/functions/extensions filters. It never registers 'timber/twig/environment/options', so Timber's default 'autoescape' => false stands.
- includes/Runtime/Twig.php:114-128 registers bem() and add_attributes() with 'is_safe' => ['html'] (their AttributeBag output must remain unescaped).
- Raw-HTML sinks in parent templates that WILL double-escape once autoescape is on (there are currently NO |raw filters anywhere in templates/):
  - templates/single.twig:67  -> {{ post.content }}
  - templates/page.twig:27     -> {{ post.content }}
  - templates/partials/comment.twig:29 -> {{ comment.content|wpautop }}
  - templates/partials/tease.twig:34   -> {{ post.excerpt }}
- includes/Blocks/AcfBlocks.php:207-215 builds $context with raw $this->fields() (get_fields()) and Timber::render()s it.
- includes/Blocks/AcfBlocks.php:665-675 template() prefers $block['data']['twig_template'] (persisted in the block comment JSON, survives KSES) over the registered $block['twig_template']; the persisted copy is seeded in block_args() around AcfBlocks.php:256-258.
- includes/Blocks/CoreBlockTwigRenderer.php:112-119 injects raw $attributes/$content into context (already opt-in, default off).

TASKS
1. In includes/Runtime/Twig.php, register a new filter in register(): add_filter('timber/twig/environment/options', [$this, 'environment_options']). Implement environment_options(array $options): array that sets $options['autoescape'] = 'html' (preserve other keys). Add a doc-block explaining the security rationale.
2. Audit EVERY file in templates/ for outputs that return HTML and add |raw ONLY to genuinely-safe HTML sinks:
   - single.twig:67 and page.twig:27: {{ post.content|raw }}
   - partials/comment.twig:29: {{ comment.content|wpautop|raw }}
   - partials/tease.twig:34: {{ post.excerpt|raw }}
   - Re-check partials/head.twig, partials/footer.twig, partials/pagination.twig, partials/tease-post.twig, layouts/base.twig and any others. Leave translated strings ({{ function('__', ...) }}) and add_attributes()/bem() calls WITHOUT |raw (they must be escaped or are already safe). function('wp_head')/function('wp_footer')/function('wp_body_open') echo directly and need no |raw.
3. Confirm bem()/add_attributes() still render unescaped after autoescape is enabled (they return an is_safe:html AttributeBag). If Timber's is_safe handling requires it, verify via the smoke test in step 6.
4. Fix S2 in includes/Blocks/AcfBlocks.php: prefer the registered $block['twig_template'] over $block['data']['twig_template'] in template(), and validate the resolved template against the set of discovered component templates before rendering. Stop seeding $args['data']['twig_template'] in block_args() (keep the top-level $args['twig_template']).
5. Update docs/timber-and-twig-authoring.md: document that autoescape is ON, that component authors get escaping by default, and when to use |raw (already-rendered WordPress HTML only). Note bem()/add_attributes() are safe to print directly.
6. Add a behavioral test that reflects the REAL runtime (autoescape ON), not the existing autoescape=false smokes:
   - Create .github/scripts/twig-autoescape-smoke.php that builds a Twig env with 'autoescape' => 'html', registers the theme's functions/extension, renders a template using {{ fields.heading }} with fields.heading = '"><script>alert(1)</script>', and asserts the output is escaped (no literal <script>). Also assert {{ add_attributes({class:['x']}) }} and {{ bem('card',['featured']) }} still emit unescaped, correct attribute HTML.
   - Add an npm script "smoke:twig-autoescape": "php .github/scripts/twig-autoescape-smoke.php" in package.json.
   - Wire it into .github/scripts/pr-validation.cjs (add a run(...) line) so it runs in the PR gate.
   - Update .github/scripts/release-check.cjs Required files list and add a check that the autoescape filter is registered (e.g. twig.includes("timber/twig/environment/options")).

ACCEPTANCE CRITERIA
- includes/Runtime/Twig.php registers timber/twig/environment/options with autoescape 'html'.
- npm run smoke:twig-autoescape passes and proves hostile field values are escaped while helpers stay unescaped.
- Manual render of a page/single/comment shows correct (not double-escaped) content — verify with the WordPress fixture (npm run release:check with WP_SMOKE_REQUIRED=1 if you have WP-CLI+MySQL) or a local WP.
- npm run lint:php (phpcs + phpstan) passes.

COMMIT AND PUSH
- git checkout -b fix/twig-autoescape (from release-2.x)
- Commit (Conventional Commits): fix(security): enable Twig autoescaping and escape ACF/block context
  Body: note S1/S2 from the release review; list the |raw sinks touched.
- git push -u origin fix/twig-autoescape and open a PR targeting release-2.x.
```

---

## Prompt 2 — Fix the release/PR audit gate (Release engineering: R1)

```text
SUMMARY
Both CI workflows run a full `npm audit` (all severities, including devDependencies) as a hard step. It currently exits 1 because of a high-severity advisory in fast-uri (a transitive dev dependency via @commitlint/cli -> ajv). This blocks the semantic-release pipeline from publishing and turns PR checks red, even though the theme ships no runtime JavaScript.

GOAL
- The dev-dependency advisory is resolved so `npm audit` is clean, AND the audit gate is made resilient so future dev-only advisories do not block releases.
- `npm audit --omit=dev` exits 0 and is the blocking gate; full `npm audit` becomes informational (non-blocking).

CONTEXT (verified)
- Reproduced locally: `npm audit` -> exit 1; `npm audit --omit=dev` -> exit 0.
- Dependency path: emulsify-wordpress -> @commitlint/cli@19.8.1 -> @commitlint/load -> @commitlint/config-validator -> ajv@8.20.0 -> fast-uri@3.1.2 (GHSA-v2hh-gcrm-f6hx / GHSA-4c8g-83qw-93j6).
- .github/workflows/theme-readiness.yml:66-70 has "Run runtime npm audit" (--omit=dev) then "Run full npm audit" (npm audit).
- .github/workflows/semantic-release.yml:73-74 and :94-95 run the same; the full audit is the LAST step of release-readiness, which semantic-release `needs:`.
- NOTE: .github/scripts/release-check.cjs:438 asserts package.json has NO "overrides" key. If you resolve the advisory with an npm "overrides" entry, you MUST relax/remove that assertion. Prefer updating dependencies so no override is needed.

TASKS
1. Resolve the advisory without adding a package.json "overrides" if possible: run `npm audit fix`, and/or bump @commitlint/cli and @commitlint/config-conventional to the latest 19.x (or newer) that pulls a patched ajv/fast-uri. Regenerate package-lock.json. Re-run `npm audit` and confirm 0 vulnerabilities.
   - If an override is truly required, add it AND update release-check.cjs:438 to allow the specific override, documenting why.
2. Make the CI gate resilient in BOTH workflows:
   - Keep `npm audit --omit=dev` as the blocking step (runtime deps).
   - Change the full `npm audit` step to non-blocking: add `continue-on-error: true` (or convert to `npm audit || true` with a clear echo), so a future dev-only advisory reports but does not fail the release.
   - Apply to .github/workflows/theme-readiness.yml and .github/workflows/semantic-release.yml.
3. Verify `node --check release.config.js` still passes and the release dry run step ordering is unaffected.

ACCEPTANCE CRITERIA
- `npm audit` -> 0 vulnerabilities (preferred) OR full audit is non-blocking and `npm audit --omit=dev` -> exit 0.
- No new package.json "overrides" unless release-check.cjs is updated accordingly.
- Both workflows: runtime audit is the hard gate; full audit is informational.

COMMIT AND PUSH
- git checkout -b fix/npm-audit-release-gate (from release-2.x)
- Commit: fix(ci): unblock release by scoping npm audit gate and patching fast-uri advisory
- git push -u origin fix/npm-audit-release-gate and open a PR targeting release-2.x.
```

---

## Prompt 3 — Harden the WP-CLI child-theme generator (C1, D1, D2, docs L3)

```text
SUMMARY
The `wp emulsify` generator has three problems: (C1) the human theme name is written unsanitized into the generated functions.php docblock and style.css header, allowing "*/"-style breakout / code injection; (D1) the --force safety guard treats project.generatedFrom as optional, so it can recursively delete a legitimate WordPress Emulsify child theme that lacks that key; (D2) a mid-copy failure leaves a half-written theme with no rollback, and with --force the original was already deleted. Also, the functional --parent flag is undocumented.

GOAL
- The generated functions.php/style.css cannot be broken out of by a crafted name.
- --force refuses unless the destination is a verified emulsify-wordpress-generated child theme whose machineName matches the requested one.
- Generation is atomic: a failure never destroys the previous theme and never leaves an orphaned partial.
- --parent is documented.
- New smoke tests cover all three fixes.

CONTEXT (verified file:line, includes/Cli/GenerateChildThemeCommand.php)
- :255 replace_theme_header(...) writes the label into style.css; :296 str_replace writes the label into functions.php ('Whisk child theme hooks.' lives inside the /** ... */ docblock at whisk/functions.php:2-10).
- :801-806 get_theme_label() only runs sanitize_text_field(), which does NOT strip */, ;, $, (), or quotes.
- :787-793 convert_label_to_machine_name() safely slugifies the machine name (path traversal already prevented) - keep as-is.
- :591-648 get_destination_replacement_error(): unconditional guards are is_dir, style.css Template in {emulsify,parent}, readable project.emulsify.json, project.platform == 'wordpress', non-empty machineName. BUT :628-645 gate every generatedFrom check on array_key_exists('generatedFrom', ...), so an absent key -> return null (allowed).
- :149-156 generate(): with --force, remove_path($destination) runs, then copy_theme(); copy_theme() returns false on first failed copy()/mkdir (~:214,:226) and generate() errors with no cleanup.
- :842-852 get_parent_slug() reads --parent (sanitize_key); it is absent from the ## OPTIONS docblock (:58-76) and all docs.
- Standalone path duplicates the C1 label defect in whisk/.cli/init.js (label written into functions.php/style.css around :126-129 and :72).

TASKS
1. C1 - sanitize the label for source contexts. Add a private helper, e.g. sanitize_label_for_source(string $label): string, that removes//neutralizes comment terminators and control characters (strip '*/', backslashes, quotes, $, ;, (), newlines) or restricts to a safe character set (letters, numbers, spaces, hyphens). Use it for the values written at :255 (style.css) and :296 (functions.php). Keep the richer label only where it is safe (e.g. project.emulsify.json via JSON encoding). Apply the identical sanitization in whisk/.cli/init.js.
2. D1 - make lineage mandatory for --force. In get_destination_replacement_error(): require generatedFrom to be PRESENT and === self::GENERATED_FROM ('emulsify-wordpress') and generatedFromVersion present; additionally require the existing project.machineName to EQUAL the requested machine name. Return a clear error otherwise. (Keep refusing parent themes and non-wordpress platforms.)
3. D2 - make generation atomic. Copy into a temporary sibling directory (e.g. <destination>.tmp-<rand>) and rename() to the final path on success. On any copy failure, remove the temp dir and error without touching the existing destination. For --force, only remove/replace the existing theme AFTER the new tree is fully staged (move old aside to <destination>.bak-<rand>, rename new into place, then delete the backup; restore the backup on failure).
4. L3 - document --parent in the ## OPTIONS docblock (:58-76), docs/wp-cli-child-theme-generation.md options table, and README. State the default is `emulsify`.
5. Tests - extend .github/scripts/child-theme-generator-smoke.php:
   - Hostile name: generate with a name containing '*/ echo 1; /*' and assert the written functions.php contains no '*/' breakout and (if PHP available) `php -l` parses it.
   - D1 refusal: a destination with Template: emulsify + platform: wordpress + non-empty machineName but NO generatedFrom -> assert --force REFUSES.
   - D1 machine-name mismatch: destination is a valid generated theme but machineName != requested -> assert --force refuses.
   - D2 rollback: simulate a mid-copy failure (e.g. unreadable source file or unwritable temp) and assert the original theme is intact and no partial remains.
   - Update .github/scripts/release-check.cjs "Child theme generator" static checks to reference the new guard behavior (mandatory generatedFrom) and remove/adjust any assertion that codified the old optional behavior.

ACCEPTANCE CRITERIA
- Generating with a hostile name yields a parseable functions.php and an uncorrupted style.css header.
- --force refuses when generatedFrom is absent, when generatedFrom != emulsify-wordpress, and when machineName mismatches.
- A simulated failed generation leaves the prior theme intact with no orphaned partial.
- --parent appears in `wp help emulsify`, the CLI doc, and README.
- npm run smoke:child-theme-generator and npm run lint:php pass.

COMMIT AND PUSH
- git checkout -b fix/cli-generator-hardening (from release-2.x)
- Commit: fix(cli): sanitize theme name, require generatedFrom for --force, make generation atomic
- git push -u origin fix/cli-generator-hardening and open a PR targeting release-2.x.
```

---

## Prompt 4 — Publish an installable release ZIP with bundled dependencies (Distribution: O1)

```text
SUMMARY
The theme's runtime depends on timber/timber (a Composer package), but the release only creates a GitHub release (git tag + notes) - no installable artifact. Composer is the primary install path, but manual download/install (and later wordpress.org) must also work. Without vendor/ bundled, a manual ZIP install has no Timber and the site degrades to the MissingTimber fallback. Build and attach a distributable ZIP that contains vendor/ (no dev deps) so manual installs work.

GOAL
- A reproducible build produces emulsify.zip containing the parent theme plus vendor/ (production dependencies only), excluding dev/build tooling.
- semantic-release attaches emulsify.zip to each GitHub release.
- README documents BOTH install paths: Composer (primary) and manual ZIP download (supported).
- Do NOT implement wordpress.org SVN deploy yet (profile not created) - just produce the installable artifact and note SVN as a future step.

CONTEXT (verified)
- includes/Bootstrap.php:99-105 loads vendor/autoload.php only if present; without it Timber is missing and MissingTimber degrades the frontend.
- .github/workflows/semantic-release.yml semantic-release job runs `npm run publish` -> semantic-release with @semantic-release/github only (see release.config.js plugins). No asset is attached.
- composer.json requires timber/timber ^2.3 (production). vendor/ is git-ignored.
- release-check.cjs enforces various packaging invariants; keep them green.

TASKS
1. Add a build script that produces the distributable ZIP:
   - Create scripts/build-dist.sh (or a Node script) that: runs `composer install --no-dev --optimize-autoloader`, then creates dist-artifact/emulsify.zip containing the theme root as a top-level `emulsify/` folder INCLUDING vendor/, includes/, templates/, functions.php, style.css, theme.json, index.php and other runtime PHP, and EXCLUDING: node_modules, whisk/ (the child starter is separate), .git, docs/ (optional), tests/smoke scripts, .github, *.dist config, package*.json, composer.* (optional), and any dev-only files. Use a WordPress-theme-appropriate file list.
   - Add an npm script "build:dist": "bash scripts/build-dist.sh".
   - Ensure the zip's internal folder name is `emulsify` (WordPress theme slug) so it installs cleanly via Appearance > Themes > Add New > Upload.
2. Attach the artifact via semantic-release: in release.config.js, configure the @semantic-release/github plugin with assets: [{ path: 'dist-artifact/emulsify.zip', label: 'Emulsify WordPress theme (with dependencies)' }].
3. In .github/workflows/semantic-release.yml, before `npm run publish` in the semantic-release job, add steps to Setup PHP (8.3) + Composer and run `npm run build:dist` so the asset exists when semantic-release runs. (Confirm @semantic-release/github can upload assets; it can.)
4. Documentation:
   - README: add an "Installation" section describing (a) Composer install (primary, tracked) and (b) manual install from the GitHub release ZIP (download emulsify.zip, upload via WordPress admin). State that the manual ZIP already includes dependencies, and that a wordpress.org listing is planned.
   - docs/release-process.md: document the artifact build and that the ZIP bundles vendor/.
5. Keep release-check.cjs green; if it needs a new assertion for the artifact/build script, add one.

ACCEPTANCE CRITERIA
- `npm run build:dist` produces dist-artifact/emulsify.zip containing vendor/ and the runtime theme, excluding dev/build files, with an internal emulsify/ folder.
- Unzipping into wp-content/themes and activating works WITHOUT running composer (Timber present; no MissingTimber notice).
- release.config.js attaches the ZIP to the GitHub release; the semantic-release job builds it first.
- README documents both install methods.

COMMIT AND PUSH
- git checkout -b feat/release-dist-artifact (from release-2.x)
- Commit: feat(release): build and attach installable theme ZIP with bundled dependencies
- git push -u origin feat/release-dist-artifact and open a PR targeting release-2.x.
```

---

## Prompt 5 — Make CI complete: un-skip fixture + Whisk a11y, and first-class PHP linting (CI unknowns, T1, phpcs/phpstan)

```text
SUMMARY
Two CI jobs are gated to skip on PRs and must be made to run and pass: "WordPress fixture smoke" (real Timber render against MySQL + WP-CLI) and "Extended Whisk Storybook and a11y". The Whisk starter is intentionally component-agnostic, so its Storybook/a11y build cannot pass without a seeded component system - that must be addressed. Also make phpcs + phpstan clearly runnable locally and a first-class, required CI check on PRs.

GOAL
- The WordPress fixture smoke runs as a required PR check and passes (gives real end-to-end render coverage - addresses review finding T1).
- The Extended Whisk Storybook + a11y job runs (at least on a schedule and manual dispatch, ideally on PRs) and passes, by seeding a component system so Storybook can build.
- phpcs + phpstan run locally via a documented command and as a dedicated, required PR job.

CONTEXT (verified)
- .github/workflows/theme-readiness.yml:
  - pull_request runs only the `practical` job (no MySQL).
  - wordpress-fixture job (:81-135) is gated `if: github.event_name != 'pull_request' && (schedule || (workflow_dispatch && inputs.wordpress_fixture))` - it already defines the mysql service and WP-CLI setup.
  - extended-whisk job (:137-159) is gated `if: github.event_name == 'workflow_dispatch' && inputs.extended_checks` and runs `npm --prefix whisk run a11y` (Storybook build + a11y).
- .github/scripts/pr-validation.cjs comment states Whisk is installed but NOT built because "a generated child theme intentionally fails Vite until a component system is installed." The same reason makes the Storybook/a11y build unable to pass without components.
- .github/scripts/wordpress-fixture-smoke.cjs prints WORDPRESS_SMOKE_SKIPPED when WP-CLI/MySQL are absent; release-check.cjs:64-67 marks that SKIP.
- package.json:30 lint:php = "vendor/bin/phpcs -q && vendor/bin/phpstan analyse --no-progress --memory-limit=1G". phpcs.xml.dist and phpstan.neon.dist exist. .husky/pre-commit runs npm run lint.

TASKS
1. Un-skip the WordPress fixture on PRs:
   - Change the wordpress-fixture job `if:` so it ALSO runs on pull_request (keep schedule/dispatch). It already has the mysql service + WP-CLI; ensure it runs `npm run release:check` with WP_SMOKE_REQUIRED=1 so the fixture is required, not skipped.
   - Consider a concurrency/runtime budget: if full fixture on every PR is too slow, gate it to run on PRs targeting main/release-2.x only. Make it a required status check for merging into release-2.x/main.
   - Confirm .github/scripts/wordpress-fixture-smoke.cjs renders the routes (home/page/single/archive/search/author/404) and fails on render errors.
2. Make Extended Whisk Storybook + a11y pass:
   - Investigate `npm --prefix whisk run a11y` and the Storybook config in whisk/. Because the starter ships no components (whisk/src/components/.gitkeep only), the Storybook build is empty or fails.
   - Seed a minimal component system for the CI build only: install the intended Emulsify Core 4 component set (or add a small fixture component with a *.stories.* and a Twig template) so Storybook builds and axe/a11y runs against real stories. Do this in a CI step (e.g. `npm --prefix whisk install <component system>` or copy a fixtures/ components dir) so the committed starter stays agnostic.
   - Ensure the a11y run passes (fix or document any violations in the seeded fixture).
   - Enable the job on a schedule (nightly) and workflow_dispatch at minimum; optionally on PRs if runtime is acceptable.
3. First-class PHP linting:
   - Add a dedicated `php-lint` job (or clearly named step) in theme-readiness.yml that runs on pull_request: Setup PHP 8.3 + Composer, `composer install`, then `npm run lint:php`. Make it a required check.
   - README: fix the stale description (README currently says lint:php runs `php -l`; it runs PHPCS + PHPStan). Add a "Linting / Static analysis" section documenting local usage: `composer install` then `npm run lint:php` (and `npm run lint:php:fix` for auto-fixes via phpcbf). Mention the .husky/pre-commit hook already runs it.
   - Confirm phpcs.xml.dist scopes the runtime PHP (includes/, functions.php) and phpstan.neon.dist points at the same; adjust if needed so the checks are meaningful and green.
4. Update docs/release-process.md and docs/README.md to describe which checks run on PRs vs schedule/dispatch after these changes.

ACCEPTANCE CRITERIA
- On a PR (to release-2.x/main), the WordPress fixture smoke actually runs (not skipped) and passes; it fails if a route render errors.
- The Extended Whisk Storybook + a11y job runs and passes with a seeded component system; the committed Whisk starter remains component-agnostic.
- A required php-lint PR check runs `npm run lint:php` and passes.
- README accurately documents local phpcs/phpstan usage.

COMMIT AND PUSH
- git checkout -b ci/complete-readiness-gates (from release-2.x)
- Commit: ci: run WordPress fixture and Whisk a11y on PRs and add required PHP lint gate
- git push -u origin ci/complete-readiness-gates and open a PR targeting release-2.x.
```

---

## Prompt 6 — Observability and documentation cleanup (B1, B2, A1, L1, L2, L9)

```text
SUMMARY
Lower-risk but valuable hardening: several failure paths are silent (missing assets, corrupt child manifest), an editor-policy option can be mistaken for a security boundary, and a few small doc/perf/whitespace nits remain.

GOAL
- Silent asset/manifest failures produce a WP_DEBUG log.
- A corrupt child manifest falls through to the parent instead of disabling manifest mode.
- Docs clarify the UI-only editor-policy flag.
- Small nits fixed.

CONTEXT (verified file:line)
- includes/Support/AssetManifest.php:377-381 returns null for missing files with no log; :423-462 returns null on unreadable/malformed child manifest instead of continuing to the parent candidate.
- includes/Blocks/AcfBlocks.php:465-469 silently skips missing asset files.
- includes/Editor/UserPatternPermissions.php:42-51 sets enableUserPatterns=false (UI only); real enforcement needs the separate restrict_wp_block_creation option.
- .github/PULL_REQUEST_TEMPLATE.md:4 has trailing whitespace (git diff --check).
- README (around the tooling/theming section) says lint:php runs `php -l` (it runs PHPCS + PHPStan) - fix if not already fixed in Prompt 5.
- includes/Editor/PolicyOptions.php:21-54 get() is not memoized; it re-runs apply_filters per block registration (inconsistent with the PR's memoization elsewhere).

TASKS
1. B1: In AssetManifest.php:377-381 and AcfBlocks.php:465-469, when a referenced asset file is not readable, error_log a clear message (e.g. "Emulsify: asset not readable: {path}") guarded by WP_DEBUG before returning null. Mirror the pattern used in includes/Support/Diagnostics.php.
2. B2: In AssetManifest.php:423-462, on an unreadable or malformed (JSON error) manifest candidate, `continue` to the next candidate instead of `return null`, so a valid parent manifest is still used. Only return null after all candidates fail.
3. A1: In docs/editor-policy.md and the UserPatternPermissions.php class doc-block, state clearly that disable_user_patterns_for_non_admins affects only the editor UI and must be paired with restrict_wp_block_creation for server-side enforcement (REST create still possible otherwise). Optionally, have the UI flag imply the capability restriction.
4. L1: Trim the trailing whitespace at .github/PULL_REQUEST_TEMPLATE.md:4 (verify with `git diff --check`).
5. L2: Correct the README lint:php description (PHPCS + PHPStan) if not already done in Prompt 5.
6. L9 (optional perf): Memoize PolicyOptions::get() per context (or per-request when context is null), matching includes/Editor/Enhancements.php config() and AssetManifest manifest() memoization.
7. If any *-smoke.php or release-check.cjs assertions cover these paths, extend them (e.g. assert the manifest fallthrough behavior; assert missing-asset logging under WP_DEBUG).

ACCEPTANCE CRITERIA
- Missing assets and corrupt manifests are logged under WP_DEBUG; a corrupt child manifest no longer disables the parent manifest.
- docs/editor-policy.md clearly distinguishes UI toggle vs enforcement.
- `git diff --check` is clean; README lint:php description is accurate.
- npm run lint:php and the smoke suite pass.

COMMIT AND PUSH
- git checkout -b chore/observability-and-docs (from release-2.x)
- Commit: fix(runtime): log silent asset failures, fall through corrupt manifests, and clarify editor-policy docs
- git push -u origin chore/observability-and-docs and open a PR targeting release-2.x.
```

---

## After all prompts land

1. Run the full local suite on PHP 8.3 + Node 24: `npm ci --ignore-scripts && composer install && npm run pr:check && npm run release:check`.
2. Trigger the "WordPress Theme Readiness" workflow manually with `wordpress_fixture=true` (and `extended_checks=true` once) and confirm all jobs pass.
3. Re-run the release dry run: `npm run publish-test -- --no-ci` and confirm it targets `2.0.0`.
4. Then proceed with the PR #29 merge into `main` to publish 2.0.0.
