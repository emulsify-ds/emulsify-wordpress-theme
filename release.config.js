const { execFileSync } = require('node:child_process');

const parserOpts = {
  // semantic-release's default parser does not treat "feat!:" consistently
  // across the release lines this project supports, so keep the breaking-change
  // header patterns explicit.
  headerPattern: /^(\w*)(?:\(([\w$.\-*/ ]*)\))?!?: (.*)$/,
  headerCorrespondence: ['type', 'scope', 'subject'],
  breakingHeaderPattern: /^(\w*)(?:\(([\w$.\-*/ ]*)\))?!: (.*)$/,
  noteKeywords: ['BREAKING CHANGE', 'BREAKING CHANGES', 'BREAKING-CHANGE'],
};

const expectedStableRelease = '2.0.0';
const initialStableBaseline = '1.0.0';
const syntheticBaselineEnvironment = 'EMULSIFY_SYNTHETIC_STABLE_BASELINE';
const syntheticBaselineShaEnvironment = 'EMULSIFY_SYNTHETIC_STABLE_BASELINE_SHA';
const syntheticBaselineSourceEnvironment = 'EMULSIFY_SYNTHETIC_STABLE_BASELINE_SOURCE';

function parseVersionParts(version) {
  return String(version || '')
    .split('-')[0]
    .split('.')
    .map((part) => Number.parseInt(part, 10));
}

function isAtLeastVersion(version, minimumVersion) {
  const current = parseVersionParts(version);
  const minimum = parseVersionParts(minimumVersion);

  if (
    current.length !== 3 ||
    minimum.length !== 3 ||
    current.some(Number.isNaN) ||
    minimum.some(Number.isNaN)
  ) {
    return false;
  }

  for (let index = 0; index < current.length; index += 1) {
    if (current[index] > minimum[index]) {
      return true;
    }

    if (current[index] < minimum[index]) {
      return false;
    }
  }

  return true;
}

function removeSyntheticBaseline(cwd) {
  const baselineTag = process.env[syntheticBaselineEnvironment];
  const expectedSha = process.env[syntheticBaselineShaEnvironment];

  if (baselineTag !== initialStableBaseline || !expectedSha) {
    return;
  }

  try {
    const actualSha = execFileSync('git', ['rev-parse', '--verify', `${baselineTag}^{commit}`], {
      cwd: cwd || process.cwd(),
      encoding: 'utf8',
    }).trim();

    if (actualSha !== expectedSha) {
      throw new Error(`${baselineTag} points to ${actualSha}, not the temporary baseline commit ${expectedSha}.`);
    }

    execFileSync('git', ['tag', '--delete', baselineTag], {
      cwd: cwd || process.cwd(),
      stdio: 'ignore',
    });
    delete process.env[syntheticBaselineEnvironment];
    delete process.env[syntheticBaselineShaEnvironment];
  }
  catch (error) {
    throw new Error(`Unable to remove the temporary ${baselineTag} release baseline tag before publishing.`, {
      cause: error,
    });
  }
}

const expectedStableReleaseGuard = {
  verifyRelease(pluginConfig, { branch, cwd, lastRelease = {}, nextRelease }) {
    try {
      // The 2.x release branch prepares the stable 2.0.0 baseline. After that
      // baseline exists, normal semantic-release versioning can continue.
      if (branch.name !== 'main' || isAtLeastVersion(lastRelease.version, expectedStableRelease)) {
        return;
      }

      if (nextRelease.version !== expectedStableRelease) {
        const message = `Expected semantic-release to prepare ${expectedStableRelease} on main, but computed ${nextRelease.version}. Confirm stable baseline tags and breaking-change commits before publishing.`;
        throw new Error(message);
      }
    }
    finally {
      // semantic-release pushes every local tag. Remove the temporary baseline
      // after version calculation, regardless of whether the guard accepts it.
      removeSyntheticBaseline(cwd);
    }
  },
};

const expectedStableReleaseAnalyzer = {
  analyzeCommits(pluginConfig, { branch, lastRelease = {} }) {
    // The release-2.x branch is the first stable release line for the rebuilt
    // parent theme. The runner temporarily exposes the latest 1.0.0 alpha as a
    // local 1.0.0 baseline so semantic-release can calculate 2.0.0.
    return branch.name === 'main' && !isAtLeastVersion(lastRelease.version, expectedStableRelease)
      ? 'major'
      : null;
  },
};

function finalizeReleaseNotesContext(context) {
  if (
    context.version === expectedStableRelease
    && context.previousTag === initialStableBaseline
    && process.env[syntheticBaselineSourceEnvironment]?.startsWith(`${initialStableBaseline}-`)
  ) {
    // The temporary baseline is never published, so do not emit a compare URL
    // whose left-hand tag would not exist on GitHub.
    context.linkCompare = false;
  }

  return context;
}

module.exports = {
  expectedStableRelease,
  initialStableBaseline,
  tagFormat: '${version}',
  branches: ['main'],
  repositoryUrl: 'git@github.com:emulsify-ds/emulsify-wordpress.git',
  plugins: [
    expectedStableReleaseAnalyzer,
    ['@semantic-release/commit-analyzer', { preset: 'angular', parserOpts }],
    [
      '@semantic-release/release-notes-generator',
      {
        preset: 'angular',
        parserOpts,
        writerOpts: {
          commitsSort: ['subject', 'scope'],
          finalizeContext: finalizeReleaseNotesContext,
        },
      },
    ],
    expectedStableReleaseGuard,
    [
      '@semantic-release/github',
      {
        assets: [
          {
            path: 'dist-artifact/emulsify.zip',
            label: 'Emulsify WordPress theme (with dependencies)',
          },
        ],
      },
    ],
  ],
};
