#!/usr/bin/env node

const { execFileSync, spawnSync } = require('node:child_process');
const path = require('node:path');

const repoRoot = path.resolve(__dirname, '..', '..');
const {
  expectedStableRelease,
  initialStableBaseline,
} = require(path.join(repoRoot, 'release.config.js'));
const syntheticBaselineEnvironment = 'EMULSIFY_SYNTHETIC_STABLE_BASELINE';
const syntheticBaselineShaEnvironment = 'EMULSIFY_SYNTHETIC_STABLE_BASELINE_SHA';
const syntheticBaselineSourceEnvironment = 'EMULSIFY_SYNTHETIC_STABLE_BASELINE_SOURCE';

function git(args, options = {}) {
  const output = execFileSync('git', args, {
    cwd: repoRoot,
    encoding: 'utf8',
    ...options,
  });

  return typeof output === 'string' ? output.trim() : '';
}

function mergedTags() {
  const output = git(['tag', '--merged', 'HEAD', '--sort=-version:refname']);

  return output === '' ? [] : output.split(/\r?\n/);
}

function publishedTags() {
  const output = git(['ls-remote', '--tags', 'origin']);
  const tags = new Set();

  for (const line of output.split(/\r?\n/)) {
    const ref = line.trim().split(/\s+/)[1] || '';
    if (ref.startsWith('refs/tags/')) {
      tags.add(ref.slice('refs/tags/'.length).replace(/\^\{\}$/, ''));
    }
  }

  return tags;
}

function tagCommit(tag) {
  try {
    return git(['rev-parse', '--verify', `${tag}^{commit}`]);
  }
  catch {
    return null;
  }
}

function prepareStableBaseline() {
  const branch = process.env.GITHUB_REF_NAME || git(['branch', '--show-current']);

  if (branch !== 'main') {
    return null;
  }

  const tags = mergedTags();
  const stableTags = tags.filter((tag) => /^\d+\.\d+\.\d+$/.test(tag));

  if (stableTags.length > 0) {
    const remoteTags = publishedTags();
    const localOnlyTags = stableTags.filter((tag) => !remoteTags.has(tag));

    if (localOnlyTags.length > 0) {
      throw new Error(
	`Refusing to publish local-only stable tag(s): ${localOnlyTags.join(', ')}. Remove them or publish them intentionally first.`
      );
    }

    return null;
  }

  const prereleaseTag = tags.find((tag) => tag.startsWith(`${initialStableBaseline}-`));

  if (!prereleaseTag) {
    throw new Error(
      `Cannot prepare ${expectedStableRelease}: no stable tag or merged ${initialStableBaseline} prerelease tag was found.`
    );
  }

  const sourceSha = tagCommit(prereleaseTag);
  if (!sourceSha) {
    throw new Error(`Cannot resolve ${prereleaseTag} to a commit for the temporary stable baseline.`);
  }

  git(['tag', initialStableBaseline, sourceSha]);
  return {
    sha: sourceSha,
    source: prereleaseTag,
    tag: initialStableBaseline,
  };
}

function removeStableBaseline(baseline) {
  if (!baseline) {
    return;
  }

  const existingSha = tagCommit(baseline.tag);
  if (!existingSha) {
    return;
  }

  if (existingSha !== baseline.sha) {
    throw new Error(
      `Refusing to delete ${baseline.tag}: it no longer points to the temporary baseline commit ${baseline.sha}.`
    );
  }

  git(['tag', '--delete', baseline.tag], { stdio: 'ignore' });
}

function main() {
  let temporaryBaseline = null;

  try {
    temporaryBaseline = prepareStableBaseline();

    const cli = require.resolve('semantic-release/bin/semantic-release.js');
    const result = spawnSync(process.execPath, [cli, ...process.argv.slice(2)], {
      cwd: repoRoot,
      env: {
	...process.env,
	[syntheticBaselineEnvironment]: temporaryBaseline?.tag || '',
	[syntheticBaselineShaEnvironment]: temporaryBaseline?.sha || '',
	[syntheticBaselineSourceEnvironment]: temporaryBaseline?.source || '',
      },
      stdio: 'inherit',
    });

    if (result.error) {
      throw result.error;
    }

    if (
      result.status === 0
      && temporaryBaseline
      && tagCommit(temporaryBaseline.tag)
    ) {
      throw new Error(
	`Semantic-release completed without removing the temporary ${temporaryBaseline.tag} baseline tag.`
      );
    }

    return Number.isInteger(result.status) ? result.status : 1;
  }
  finally {
    removeStableBaseline(temporaryBaseline);
  }
}

try {
  process.exitCode = main();
}
catch (error) {
  console.error(`Semantic release runner failed: ${error.message}`);
  process.exitCode = 1;
}
