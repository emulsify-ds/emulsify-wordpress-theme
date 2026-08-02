# Emulsify WordPress 2.0 — Fix Failing PR Checks + Remaining Prompts

Follow-up after Prompt A restored GitHub Actions on PR #29. The checks now run and some fail.

**How this was diagnosed:** `release-2.x` HEAD is unchanged (`1552742`) — Prompt A was a settings/re-trigger fix, not a commit — so the failing jobs run against the exact tree already reviewed. I extracted that tree and ran the Node-executable checks locally. PHP, MySQL/WP-CLI, and the Whisk Storybook build can't run in this environment, so those are guided by evidence + real CI logs rather than executed here.

---

## What I confirmed vs. what needs the CI log

### ✅ CONFIRMED failure (reproduced locally, pure Node — fails in CI too)

**`npm run release:check` throws `runtimeAuditIndex is not defined`.**
`.github/scripts/release-check.cjs` line **1096** references `runtimeAuditIndex` and `fullAuditIndex`, which are **never defined** in the "Theme readiness workflow" check. Running it:

```
FAIL Theme readiness workflow: runtimeAuditIndex is not defined
EXIT=1
```

Because `release:check` runs in the **`practical`** job, the **`wordpress-fixture`** job, and the **release pipeline's `release-readiness`** job, this single bug fails all three. It is a bug in the check script itself (left over from the audit-gate edit in Prompt 2), not in the theme. Exact fix is in Prompt E. (I confirmed every other static check in `release-check.cjs` passes and the other referenced vars — `whiskA11yEntry/Story/Template` — are defined in outer scope at lines 257-259, so this is the only JS bug.)

### ❓ Needs the real CI log (couldn't execute here)

- **`php-lint`** (`npm run lint:php` = PHPCS + PHPStan): needs PHP 8.3 + Composer. The new code (autoescape method, `sanitize_label_for_source`, atomic staging/`rename` calls, `AcfBlocks` template reorder, `AssetManifest` `error_log`) is the most likely source of new PHPCS/PHPStan violations.
- **`wordpress-fixture`**: fails first on the `release:check` bug above; after that fix it will actually render. Verify the autoescape change didn't break content output (the `|raw` sinks were added, so it should be fine).
- **`extended-whisk`** (`npm --prefix whisk run a11y`): the fixture markup is likely pa11y-clean (pa11y uses WCAG2AA/HTMLCS, which has no "needs an h1" rule), so the real risk is earlier — `npm run whisk:install` and the Vite/Storybook build succeeding with only the seeded `ci-readiness` component, and pa11y finding system Chrome.

---

## Prompt E — Fix the failing checks on PR #29

```text
SUMMARY
GitHub Actions now run on PR #29 (release-2.x -> main) and several checks fail. There is one confirmed script bug that fails npm run release:check across multiple jobs, plus jobs that must be verified and fixed against their real logs. Make all PR checks green.

GOAL
- gh pr checks 29 is all green: Practical theme readiness, PHP coding standards and static analysis, WordPress fixture smoke, Extended Whisk Storybook and a11y, and any others.

PART 1 — CONFIRMED FIX (do this first; it unblocks 3 jobs)
File: .github/scripts/release-check.cjs, in the runStaticCheck('Theme readiness workflow', ...) callback (around lines 1075-1096).
Problem: line 1096 uses runtimeAuditIndex and fullAuditIndex, which are never defined -> ReferenceError -> the check FAILs -> npm run release:check exits 1 in the practical job, the wordpress-fixture job, and the release-readiness pipeline.
Fix: define both indices before they are used. Add these two lines immediately after the existing `const extendedWhiskJob = themeReadinessWorkflow.slice(extendedWhiskStart);` line:

    const runtimeAuditIndex = themeReadinessWorkflow.indexOf('- name: Run runtime npm audit');
    const fullAuditIndex = themeReadinessWorkflow.indexOf('- name: Run full npm audit');

Then verify locally (no PHP needed for the static portion):
    node .github/scripts/release-check.cjs
Expect the "Theme readiness workflow" line to read PASS. (PHP smoke lines will SKIP/PASS depending on whether php is installed locally; that is fine.)

PART 2 — TRIAGE EVERY REMAINING FAILING JOB FROM ITS REAL LOG
For each failing check, pull the actual failure and fix the root cause. Do not guess:
    gh pr checks 29
    gh run list --branch release-2.x --limit 10
    gh run view <run-id> --log-failed        # for each failing job

Then, per job:

A) "PHP coding standards and static analysis" (php-lint -> npm run lint:php)
   - Run locally: composer install && npm run lint:php
   - Auto-fix what PHPCS can: npm run lint:php:fix (vendor/bin/phpcbf), then re-run.
   - Manually fix remaining PHPCS/PHPStan errors. Pay special attention to code added in this release:
     includes/Runtime/Twig.php (environment_options), includes/Cli/GenerateChildThemeCommand.php
     (sanitize_label_for_source, get_unique_sibling_path, replace_with_staged_theme, rename() return
     values), includes/Blocks/AcfBlocks.php (template selection), includes/Support/AssetManifest.php
     (error_log helper). Ensure rename()/mkdir()/copy() return values are handled (PHPStan) and that
     any error_log call is WP_DEBUG-guarded and passes WPCS.
   - Do NOT weaken phpcs.xml.dist / phpstan.neon.dist or add blanket ignores to make it pass; fix the code.

B) "WordPress fixture smoke" (wordpress-fixture -> release:check with WP_SMOKE_REQUIRED=1)
   - This fails first on the Part 1 bug. After Part 1, run the fixture locally (WP-CLI + MySQL, or wp-env/Lando) with:
     WP_SMOKE_REQUIRED=1 WP_SMOKE_DB_HOST=127.0.0.1 WP_SMOKE_DB_PORT=3306 WP_SMOKE_DB_NAME=wordpress_smoke WP_SMOKE_DB_USER=root WP_SMOKE_DB_PASSWORD=root npm run release:check
   - Confirm every route renders without a PHP error/exception, and that autoescape did not break output:
     post.content / comment.content / excerpt must render as real HTML (the |raw filters handle this),
     and an ACF/Twig block value like "><script>alert(1)</script> must render ESCAPED.
   - Fix any render error surfaced by wordpress-fixture-smoke.cjs.

C) "Extended Whisk Storybook and a11y" (extended-whisk -> npm --prefix whisk run a11y)
   - Reproduce locally exactly as CI does:
     npm run whisk:install
     cp -R .github/fixtures/whisk-a11y/. whisk/src/components/
     PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome npm --prefix whisk run a11y
   - Likely failure points, in order: (1) whisk:install resolving @emulsify/core@^4.1.0; (2) the Vite build
     (npm run build) or storybook build succeeding with only the seeded ci-readiness component — if the core
     Vite config needs a foundation/global entry, add the minimal required entry to the CI fixture so the build
     produces output; (3) pa11y locating Chrome (PUPPETEER_EXECUTABLE_PATH) — confirm /usr/bin/google-chrome
     exists on the runner or install Chrome in the job; (4) pa11y story discovery (resolvePa11yStoryIds must
     find the seeded story in .out). Fix whichever the log shows. If a genuine pa11y violation is reported for
     the fixture, fix the fixture markup (.github/fixtures/whisk-a11y/ci-readiness/ci-readiness.twig) rather
     than adding a blanket ignore.
   - If this job proves too heavy/flaky to gate every PR, apply Prompt B (scope it off normal PRs) instead of
     forcing it green on every PR — but it must still pass on its intended trigger.

PART 3 — VERIFY GREEN
   - Push the fixes to release-2.x (or a branch merged into it) and confirm: gh pr checks 29  -> all green.
   - Re-run any flaky job once to confirm stability.

ACCEPTANCE CRITERIA
- node .github/scripts/release-check.cjs shows "Theme readiness workflow" PASS.
- gh pr checks 29 shows all checks passing.
- No check was made to pass by weakening assertions, adding blanket lint ignores, or skipping tests; each fix addresses the root cause. (The one exception permitted: scoping the expensive extended-whisk/fixture jobs per Prompt B, which is a deliberate policy choice, not a test weakening.)

COMMIT AND PUSH
- git checkout -b fix/ci-failing-checks (from release-2.x)
- Commit 1 (Conventional Commits): fix(ci): define audit step indices in release-check theme-readiness assertion
- Commit further fixes per job, e.g.: fix(lint): resolve PHPCS/PHPStan findings in 2.0 runtime; ci: stabilize Whisk a11y build for CI
- git push -u origin fix/ci-failing-checks and open a PR targeting release-2.x (or push directly to release-2.x per your flow). Paste the green gh pr checks 29 output in the PR.
```

---

## Prompt B — Tune CI cost and robustness for the expensive jobs

```text
SUMMARY
The merged theme-readiness workflow runs the WordPress fixture (MySQL + WP-CLI) and the Whisk Storybook a11y build on EVERY pull_request, and the schedule is daily ('17 11 * * *'). This is expensive, slow per PR, and adds flaky-failure surface. Scope the expensive jobs sensibly while keeping fast pre-merge signal. NOTE: if you change job trigger conditions or names, update the corresponding assertions in .github/scripts/release-check.cjs (the "Theme readiness workflow" check asserts these exact conditions) and any branch-protection required checks.

GOAL
- practical + php-lint run on every PR (fast signal).
- wordpress-fixture + extended-whisk run where they add the most value (release-targeted PRs and/or schedule + manual dispatch), not on every PR.
- extended-whisk reliably finds Chrome; a11y violations fail the job.

CONTEXT (.github/workflows/theme-readiness.yml)
- wordpress-fixture if: github.event_name == 'pull_request' || schedule || (workflow_dispatch && inputs.wordpress_fixture)
- extended-whisk if: github.event_name == 'pull_request' || schedule || (workflow_dispatch && inputs.extended_checks)
- extended-whisk sets PUPPETEER_EXECUTABLE_PATH: /usr/bin/google-chrome
- schedule cron: '17 11 * * *' (daily)
- release-check.cjs (~lines 1104-1116) asserts wordpressFixtureJob/extendedWhiskJob include "github.event_name == 'pull_request'", the schedule condition, and their inputs. Keep the check and the workflow in sync after edits.

TASKS
1. Choose a scope and implement it consistently in the workflow AND the release-check.cjs assertions:
   Option (recommended): run the expensive jobs on PRs whose BASE is main or release-2.x, plus schedule + manual dispatch. Example gate:
     if: >-
       (github.event_name == 'pull_request' && (github.base_ref == 'main' || github.base_ref == 'release-2.x')) ||
       github.event_name == 'schedule' ||
       (github.event_name == 'workflow_dispatch' && inputs.wordpress_fixture)
   (and the analogous inputs.extended_checks for extended-whisk).
   Update the release-check.cjs assertions to match the new conditions (do not leave them asserting the old string).
2. Set the schedule to a deliberate cadence (weekly '17 11 * * 1' unless daily is intended). Update the release-check.cjs cron assertion (currently expects '17 11 * * *') to match whatever you choose.
3. Make extended-whisk's Chrome dependency reliable: verify /usr/bin/google-chrome exists on the runner; if not guaranteed, add a setup step (browser-actions/setup-chrome or apt-get install google-chrome-stable) and point PUPPETEER_EXECUTABLE_PATH at it. Confirm pa11y fails the job on real violations.
4. Align branch-protection required checks on main/release-2.x with the jobs that actually run on those PRs.

ACCEPTANCE CRITERIA
- Normal PRs run practical + php-lint quickly; expensive jobs run per the chosen scope and pass there.
- release-check.cjs "Theme readiness workflow" assertions match the edited workflow (release:check stays green).
- Schedule cadence is intentional; Chrome dependency is explicit.

COMMIT AND PUSH
- git checkout -b ci/tune-readiness-cost (from release-2.x)
- Commit: ci: scope expensive fixture and Whisk a11y jobs and keep release-check assertions in sync
- git push -u origin ci/tune-readiness-cost and open a PR targeting release-2.x.
```

---

## Prompt C — Final green-before-merge verification

```text
SUMMARY
Before merging PR #29 (release-2.x -> main), prove the full release path is green end to end on a machine with PHP 8.3 + Composer + Node 24 (and WP-CLI + MySQL for the fixture). Do this AFTER Prompt E so you are verifying a fixed tree.

GOAL
- All local gates pass, the WordPress fixture renders, the installable ZIP builds and installs without Composer, the release dry run targets 2.0.0, and gh pr checks 29 is green.

TASKS
1. Fresh install + static gates:
   - npm ci --ignore-scripts
   - composer validate --no-check-publish --strict
   - composer install --no-interaction --no-progress --prefer-dist
   - npm run lint:php            # PHPCS + PHPStan must pass
   - npm audit --omit=dev        # must exit 0
2. Behavioral checks (must pass), including the confirmed-fixed script:
   - node .github/scripts/release-check.cjs   # "Theme readiness workflow" must be PASS now
   - npm run pr:check
   - npm run smoke:twig-autoescape            # hostile field values escaped at render
   - Confirm child-theme generator smokes cover: hostile name, --force refusal when generatedFrom absent, machine-name mismatch refusal, partial-copy rollback.
3. Real WordPress render (WP-CLI + MySQL):
   - WP_SMOKE_REQUIRED=1 WP_SMOKE_DB_HOST=127.0.0.1 WP_SMOKE_DB_PORT=3306 WP_SMOKE_DB_NAME=wordpress_smoke WP_SMOKE_DB_USER=root WP_SMOKE_DB_PASSWORD=root npm run release:check
   - Manually verify page/single render is not double-escaped and an ACF/Twig block with "><script>alert(1)</script> renders escaped.
4. Distributable artifact:
   - npm run build:dist
   - Unzip dist-artifact/emulsify.zip into wp-content/themes WITHOUT composer; activate a generated child theme; confirm the parent runtime loads via Timber (no MissingTimber notice).
5. Whisk a11y (matches extended-whisk):
   - npm run whisk:install && cp -R .github/fixtures/whisk-a11y/. whisk/src/components/ && PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome npm --prefix whisk run a11y
6. Release dry run:
   - npm run publish-test -- --no-ci   # computes 2.0.0
7. Confirm the PR is green: gh pr checks 29

ACCEPTANCE CRITERIA
- Every command passes; fixture renders; escaping verified; ZIP installs without Composer; dry run targets 2.0.0; gh pr checks 29 all green.
- Post the results checklist on PR #29.

COMMIT AND PUSH
- No code changes expected. If a gate reveals a defect, fix on a branch off release-2.x (git checkout -b fix/pre-merge-<slug>), commit with a Conventional Commit message, push targeting release-2.x. Otherwise report results and proceed to merge.
```

---

## Prompt D — Post-release cleanup (defer until after 2.0.0 tags)

```text
SUMMARY
Clean up one-shot release machinery and stale metadata shortly AFTER 2.0.0 is tagged.

TASKS
1. Remove the forced-major semantic-release shim once lastRelease >= 2.0.0 exists: in release.config.js delete the custom expectedStableReleaseAnalyzer and expectedStableReleaseGuard so normal Conventional-Commit versioning resumes. Verify with npm run publish-test -- --no-ci that the next version is a normal increment. Update any release-check.cjs assertion that expects the shim.
2. Refresh WordPress compatibility: re-test against the current WordPress release and bump style.css "Tested up to" (currently 6.7); update the matching release-check.cjs assertion.
3. Optional: memoize includes/Editor/PolicyOptions.php get() per context to match Enhancements/AssetManifest.

ACCEPTANCE CRITERIA
- release.config.js no longer forces a major; dry run computes a normal increment.
- style.css "Tested up to" reflects the current tested WordPress version and release-check.cjs matches.

COMMIT AND PUSH
- git checkout -b chore/post-2.0-cleanup (from main, after 2.0.0 tags)
- Commit: chore(release): remove forced-major shim and refresh tested-up-to after 2.0.0
- git push -u origin chore/post-2.0-cleanup and open a PR targeting main.
```

---

## Bottom line

Run **Prompt E first** — its Part 1 is a two-line, verified fix that clears the confirmed `release:check` failure across the `practical`, `wordpress-fixture`, and release jobs; Part 2 has your code AI resolve `php-lint` and `extended-whisk` from their real logs (I couldn't execute PHP/Storybook here, but flagged the specific likely causes). Then **Prompt B** to stop the expensive jobs from taxing every PR (and keep `release-check.cjs` assertions in sync — a subtlety that would otherwise re-break `release:check`), **Prompt C** to verify green end-to-end, and **Prompt D** after 2.0.0 tags.

One recurring theme worth noting: `release-check.cjs` asserts on exact workflow substrings, so **every future CI edit must update those assertions in lockstep** — that coupling is exactly what produced this failure. Converting the highest-value string assertions to behavioral checks (backlog item from the original review) would prevent recurrences.
