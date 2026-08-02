# Emulsify WordPress 2.0 — Re-evaluation & Next Prompts

Follow-up to `RELEASE-REVIEW-PR29.md` and `IMPLEMENTATION-PROMPTS.md`, after the six fix branches were merged into `release-2.x`.

- Evaluated against the **actual merged `release-2.x`** on GitHub (`1552742`, fetched via HTTPS), not the stale local ref.
- Analysis only — nothing modified, committed, or pushed.

---

## 1. Status: prior recommendations were implemented correctly

I verified each fix in the merged tree. All landed, and the quality is high.

| ID | Recommendation | Status | Evidence (merged `release-2.x`) |
|----|----------------|--------|----------------------------------|
| R1 | Fix `npm audit` release gate | ✅ Done | `package-lock.json` now resolves `fast-uri@3.1.4` (advisory was ≤3.1.3); full `npm audit` is `continue-on-error: true` in both workflows (`theme-readiness.yml`, `semantic-release.yml:95`); `npm audit --omit=dev` remains the hard gate. |
| S1 | Enable Twig autoescape + fix raw sinks | ✅ Done | `includes/Runtime/Twig.php:28` registers `timber/twig/environment/options`; `:44` sets `autoescape = 'html'`. `|raw` added to `single.twig:67`, `page.twig:27`, `partials/comment.twig:29`, `partials/tease.twig:34`. New `.github/scripts/twig-autoescape-smoke.php`. |
| S2 | Stop trusting persisted `data.twig_template` | ✅ Done | `includes/Blocks/AcfBlocks.php:674-682` now prefers the registered `$block['twig_template']`; the persisted `data` copy is only a fallback. |
| C1 | Sanitize theme name in generated files | ✅ Done | `GenerateChildThemeCommand.php:885 sanitize_label_for_source()` strips everything except Unicode letters/numbers/space/hyphen (removes `*/`, `;`, `$`, quotes); used at `:251`. |
| D1 | Make `--force` `generatedFrom` mandatory | ✅ Done | `get_destination_replacement_error()` now requires `generatedFrom === 'emulsify-wordpress'` unconditionally (`:648`) **and** the existing `machineName` to equal the requested one (`:640-641`). |
| D2 | Atomic copy with rollback | ✅ Done | Generation stages into a temp sibling and `rename()`s into place (`:150-174`); `replace_with_staged_theme()` (`:666-679`) backs up the old theme and restores it on failure. |
| O1 | Bundled installable ZIP | ✅ Done | `scripts/build-dist.sh` (composer `--no-dev`, staged, cleanup trap); `package.json build:dist`; `release.config.js:92-94` attaches `dist-artifact/emulsify.zip`; `semantic-release.yml:128-129` builds it before publish. |
| CI | Un-skip fixture + Whisk a11y; PHP lint gate | ✅ Done | `theme-readiness.yml` adds a `php-lint` job and runs `wordpress-fixture` + `extended-whisk` on `pull_request`; Whisk a11y is seeded from `.github/fixtures/whisk-a11y/` so Storybook can build. |
| B1/B2/A1 | Observability + docs | ✅ Done | `AssetManifest.php:489` adds an `error_log` helper; malformed manifests now `continue` to the next candidate; editor-policy docs clarified. |

Release metadata is intact (`package.json` and `style.css` both `2.0.0`). **Net: the release content is in good shape.** The only blocker now is the CI/Actions problem below.

---

## 2. Why the GitHub Actions look "missing" — what I could and couldn't determine

**What I verified (high confidence):** All four workflow files exist on merged `release-2.x` (`addtoprojects.yml`, `contributors.yml`, `semantic-release.yml`, `theme-readiness.yml`), all are **valid YAML**, all have intact `on:` triggers, and `theme-readiness.yml` still triggers on `pull_request` with base `main` (PR #29's base) — so the files themselves would run PR #29's checks. The job that was likely your required check (`Practical theme readiness`) still exists by the same name.

**What I could NOT observe from here:** the live GitHub Actions state. `api.github.com` and the Actions HTML pages time out from this environment, so I can't see run history, a "disabled" banner, or branch-protection settings directly.

**Therefore the cause is almost certainly GitHub-side, not a broken file.** In order of likelihood:

1. **Actions disabled / restricted for the repo** (Settings → Actions → General → "Actions permissions"). This makes all workflows vanish from PRs. Most common match for "they're just gone."
2. **The PR head wasn't re-triggered by the merge.** If the branches were merged in a way that didn't push a fresh `synchronize` to `release-2.x`, no new runs start. Fix: push any commit (even empty) to `release-2.x`.
3. **Branch-protection required checks by name.** New jobs were added (`PHP coding standards and static analysis`, `WordPress fixture smoke`, `Extended Whisk Storybook and a11y`). If `main`'s protection lists required checks that no longer match, the merge box can show checks stuck as "Expected / Waiting."
4. **Unresolvable action version.** The workflows pin `actions/checkout@v7`, `actions/setup-node@v6`, `shivammathur/setup-php@v2`. If `@v7`/`@v6` don't resolve on your runners, jobs fail immediately (visible as red, not truly "missing") — worth ruling out.

Prompt A below has your code AI determine the exact cause with the `gh` CLI and fix it.

### New observations from the merged CI (worth addressing)

- **CI cost/latency:** `wordpress-fixture` (MySQL + WP-CLI) and `extended-whisk` (Storybook build + a11y) now run on **every** `pull_request` to `main`/`develop`/`release-2.x`/`emulsify-core-integration`, and the `schedule` changed from weekly to **daily** (`17 11 * * *`). That's a large jump in minutes and PR wall-clock time, and more surface for flaky failures. Consider scoping the expensive jobs to release-targeted PRs (Prompt B).
- **Chrome dependency:** `extended-whisk` sets `PUPPETEER_EXECUTABLE_PATH: /usr/bin/google-chrome`; confirm that path exists on the runner image or install Chrome in the job (Prompt B).

---

## 3. Copiable prompts for your Code AI

Priority: **Prompt A first** (unblocks CI), then **B** (tune/robustify), then **C** (final pre-merge verification). Prompt D is a post-release cleanup you can defer.

---

### Prompt A — Diagnose and restore the missing GitHub Actions

```text
SUMMARY
After merging the release fix branches into release-2.x, GitHub Actions checks are no longer appearing on PR #29 (release-2.x -> main). The workflow files themselves are present and valid (verified: .github/workflows/theme-readiness.yml, semantic-release.yml, addtoprojects.yml, contributors.yml are valid YAML with correct on: triggers, and theme-readiness triggers on pull_request with base main). The cause is therefore GitHub-side. Diagnose the exact reason and restore checks on the PR.

GOAL
- Determine why no workflow runs are being created for PR #29 and fix it.
- Confirm "WordPress Theme Readiness" runs on the PR and its jobs complete.

TASKS
1. Confirm repo Actions are enabled and permissioned:
   - gh api repos/emulsify-ds/emulsify-wordpress/actions/permissions
   - If "enabled" is false or restricted, re-enable (Settings > Actions > General > Allow all actions, or the org's required setting). Report the current value.
2. List workflows and their state (active/disabled):
   - gh workflow list --all
   - If "WordPress Theme Readiness" shows disabled_manually or disabled_inactivity, re-enable it: gh workflow enable "WordPress Theme Readiness" (or by file: gh workflow enable theme-readiness.yml).
3. Inspect recent runs and the PR checks:
   - gh run list --branch release-2.x --limit 20
   - gh pr checks 29
   - Capture whether runs exist for the latest release-2.x SHA and, if any failed, the failure reason.
4. Lint the workflows against the GitHub Actions schema (catches issues plain YAML validation misses):
   - Install actionlint (e.g. `bash <(curl -s https://raw.githubusercontent.com/rhysd/actionlint/main/scripts/download-actionlint.bash)` or via your package manager) and run: ./actionlint
   - Fix any errors it reports in .github/workflows/*.yml.
5. Verify pinned action versions resolve on your runners: actions/checkout@v7, actions/setup-node@v6, shivammathur/setup-php@v2. If any major tag does not exist, pin to an existing major (e.g. actions/checkout@v4, actions/setup-node@v4) across ALL workflow files.
6. Check branch protection on main for required status checks whose names no longer match current jobs:
   - gh api repos/emulsify-ds/emulsify-wordpress/branches/main/protection --jq '.required_status_checks.checks'
   - If required checks reference removed/renamed jobs, update the required-check list to the current job names: "Practical theme readiness", "PHP coding standards and static analysis", "WordPress fixture smoke", "Extended Whisk Storybook and a11y". (Do not require the expensive jobs if Prompt B narrows them.)
7. Re-trigger checks on the PR: push an empty commit to release-2.x
   - git commit --allow-empty -m "ci: re-trigger theme readiness checks" && git push origin release-2.x
   - Then confirm with: gh run list --branch release-2.x --limit 5  and  gh pr checks 29

ACCEPTANCE CRITERIA
- gh pr checks 29 shows "WordPress Theme Readiness" jobs running/completed (not empty).
- Repo Actions permissions confirmed enabled; the workflow is enabled.
- actionlint reports no errors; all pinned action versions resolve.
- A written summary of the ROOT CAUSE and the exact fix applied.

COMMIT AND PUSH
- Work on a branch off release-2.x: git checkout -b ci/restore-actions
- Commit any workflow/version fixes (Conventional Commits): fix(ci): restore GitHub Actions on release PR (root cause: <cause>)
- git push -u origin ci/restore-actions and open a PR targeting release-2.x (or push the empty re-trigger commit directly to release-2.x if that is your flow).
- In the PR description, state the root cause and paste the gh pr checks 29 output showing checks now present.
```

---

### Prompt B — Tune CI cost and robustness for the expensive jobs

```text
SUMMARY
The merged theme-readiness workflow now runs the WordPress fixture (MySQL + WP-CLI) and the Whisk Storybook a11y build on EVERY pull_request, and the schedule changed to daily. This is expensive and increases flaky-failure surface and PR wall-clock time. Make the expensive coverage reliable and appropriately scoped without losing pre-merge signal on release branches.

GOAL
- Keep fast checks (practical + php-lint) on every PR.
- Run the expensive jobs (wordpress-fixture, extended-whisk) where they add the most value without taxing every PR.
- Ensure extended-whisk's headless Chrome dependency is satisfied.

CONTEXT (.github/workflows/theme-readiness.yml)
- practical and php-lint jobs: keep on all PRs (fast).
- wordpress-fixture if: currently github.event_name == 'pull_request' || schedule || (workflow_dispatch && inputs.wordpress_fixture).
- extended-whisk if: currently github.event_name == 'pull_request' || schedule || (workflow_dispatch && inputs.extended_checks).
- extended-whisk sets PUPPETEER_EXECUTABLE_PATH: /usr/bin/google-chrome.
- schedule cron is '17 11 * * *' (daily).

TASKS
1. Scope the expensive jobs to release-relevant PRs instead of all PRs. Options (pick one and implement):
   a) Restrict to PRs whose BASE is main or release-2.x, e.g. gate with: (github.event_name == 'pull_request' && contains(fromJSON('["main","release-2.x"]'), github.base_ref)) || github.event_name == 'schedule' || (github.event_name == 'workflow_dispatch' && inputs.<flag>).
   b) OR keep them off normal PRs and run on merges to release branches + schedule + manual dispatch.
   Document the choice in the workflow comments.
2. Reconsider the daily schedule: unless daily is intended, set the cron back to weekly (e.g. '17 11 * * 1') to control cost. Keep manual workflow_dispatch for on-demand full runs.
3. Make extended-whisk's Chrome dependency explicit and reliable: verify /usr/bin/google-chrome exists on ubuntu-latest; if not guaranteed, add a step to install Chrome (e.g. browser-actions/setup-chrome or apt-get install google-chrome-stable) and set PUPPETEER_EXECUTABLE_PATH accordingly. Ensure the a11y run fails the job on real accessibility violations (not silently passing).
4. Add sensible timeouts (already present: 10-20 min) and confirm concurrency cancels superseded PR runs (cancel-in-progress for pull_request is already set).
5. If Prompt A updated branch protection, align required checks with this scoping (do not require a job that no longer runs on normal PRs).

ACCEPTANCE CRITERIA
- Normal PRs run practical + php-lint quickly; the expensive jobs run only per the chosen scope (release PRs and/or schedule/dispatch) and PASS there.
- extended-whisk reliably finds Chrome and reports a11y violations as failures.
- Schedule frequency is intentional and documented.

COMMIT AND PUSH
- git checkout -b ci/tune-readiness-cost (from release-2.x)
- Commit: ci: scope expensive fixture and Whisk a11y jobs and stabilize Chrome for a11y
- git push -u origin ci/tune-readiness-cost and open a PR targeting release-2.x.
```

---

### Prompt C — Final green-before-merge verification

```text
SUMMARY
Before merging PR #29 (release-2.x -> main), prove the full release path is green end to end on a machine with PHP 8.3 + Composer + Node 24 (and WP-CLI + MySQL for the fixture). This confirms the merged fixes actually work at runtime, not just in source.

GOAL
- All local gates pass, the WordPress fixture renders, the installable ZIP builds and installs without Composer, and the release dry run targets 2.0.0.

TASKS
1. Fresh install and static gates:
   - npm ci --ignore-scripts
   - composer validate --no-check-publish --strict
   - composer install --no-interaction --no-progress --prefer-dist
   - npm run lint:php            # PHPCS + PHPStan must pass
   - npm audit --omit=dev        # must exit 0 (runtime clean)
   - npm audit                   # informational; confirm the only findings are dev-only
2. Behavioral smokes (must pass), including the new security test:
   - npm run pr:check
   - npm run smoke:twig-autoescape   # confirms hostile ACF/field values are escaped at render
   - Confirm the child-theme generator smokes cover: hostile name, --force refusal when generatedFrom is absent, machine-name mismatch refusal, and partial-copy rollback.
3. Real WordPress render (needs WP-CLI + MySQL):
   - Run: WP_SMOKE_REQUIRED=1 WP_SMOKE_DB_HOST=127.0.0.1 WP_SMOKE_DB_PORT=3306 WP_SMOKE_DB_NAME=wordpress_smoke WP_SMOKE_DB_USER=root WP_SMOKE_DB_PASSWORD=root npm run release:check
   - Manually verify a page/single render is NOT double-escaped (post.content shows real HTML) and that an ACF/Twig block with a value like '"><script>alert(1)</script>' renders ESCAPED.
4. Distributable artifact:
   - npm run build:dist
   - Unzip dist-artifact/emulsify.zip into a WordPress install's wp-content/themes WITHOUT running composer; activate a generated child theme; confirm the parent runtime loads via Timber (no MissingTimber notice) because vendor/ is bundled.
5. Release dry run:
   - npm run publish-test -- --no-ci   # confirm it computes 2.0.0
6. Trigger the CI "WordPress Theme Readiness" workflow (workflow_dispatch with wordpress_fixture=true and extended_checks=true once) and confirm all jobs pass on the runner, not just locally.

ACCEPTANCE CRITERIA
- Every command above passes; the fixture renders all routes without error; escaping verified; ZIP installs without Composer; dry run targets 2.0.0; CI dispatch is green.
- Post a short checklist of results on PR #29.

COMMIT AND PUSH
- No code changes expected. If a gate reveals a defect, fix on a branch off release-2.x (git checkout -b fix/pre-merge-<slug>), commit with a Conventional Commit message, and push targeting release-2.x. Otherwise, report results on the PR and proceed to merge.
```

---

### Prompt D — Post-release cleanup (defer until after 2.0.0 tags)

```text
SUMMARY
Two items should be cleaned up shortly AFTER 2.0.0 is tagged, so they don't linger as debt.

GOAL
- Remove one-shot release machinery and refresh stale metadata once the stable baseline exists.

TASKS
1. Remove the forced-major semantic-release shim after 2.0.0 is published: in release.config.js, once lastRelease.version >= 2.0.0 exists, delete the custom expectedStableReleaseAnalyzer and expectedStableReleaseGuard (release.config.js:65-88) so normal Conventional-Commit versioning resumes. Verify with npm run publish-test -- --no-ci that the next computed version is a normal increment (e.g. 2.0.1 / 2.1.0), not a forced major.
2. Refresh WordPress compatibility metadata: re-test against the current WordPress release and bump style.css "Tested up to" (currently 6.7) accordingly; update the matching assertion in .github/scripts/release-check.cjs.
3. Optional: memoize includes/Editor/PolicyOptions.php get() per context to match the memoization used in Enhancements/AssetManifest (minor perf consistency).

ACCEPTANCE CRITERIA
- release.config.js no longer forces a major; dry run computes a normal increment.
- style.css "Tested up to" reflects the current tested WordPress version and release-check.cjs matches.

COMMIT AND PUSH
- git checkout -b chore/post-2.0-cleanup (from main, after 2.0.0 tags)
- Commit: chore(release): remove forced-major shim and refresh tested-up-to after 2.0.0
- git push -u origin chore/post-2.0-cleanup and open a PR targeting main.
```

---

## 4. Bottom line

The merged `release-2.x` correctly implements every prior recommendation and the release content is ready. The remaining blocker is operational: **restore the GitHub Actions on PR #29** (Prompt A) — the workflow files are sound, so this is a repo-settings / re-trigger / branch-protection issue. Then tune CI cost (Prompt B), run the final green verification (Prompt C), and merge. Defer Prompt D until after 2.0.0 tags.
