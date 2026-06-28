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

const expectedStableReleaseGuard = {
  verifyRelease(pluginConfig, { branch, lastRelease = {}, nextRelease }) {
    // The 2.x release branch prepares the stable 2.0.0 baseline. After that
    // baseline exists, normal semantic-release versioning can continue.
    if (branch.name !== 'main' || isAtLeastVersion(lastRelease.version, expectedStableRelease)) {
      return;
    }

    if (nextRelease.version !== expectedStableRelease) {
      const message = `Expected semantic-release to prepare ${expectedStableRelease} on main, but computed ${nextRelease.version}. Confirm stable baseline tags and breaking-change commits before publishing.`;
      throw new Error(message);
    }
  },
};

module.exports = {
  expectedStableRelease,
  tagFormat: '${version}',
  branches: ['main'],
  repositoryUrl: 'git@github.com:emulsify-ds/emulsify-wordpress-theme.git',
  plugins: [
    ['@semantic-release/commit-analyzer', { parserOpts }],
    ['@semantic-release/release-notes-generator', { parserOpts }],
    expectedStableReleaseGuard,
    ['@semantic-release/npm', { npmPublish: false }],
    '@semantic-release/github',
  ],
};
