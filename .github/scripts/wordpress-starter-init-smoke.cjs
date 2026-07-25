#!/usr/bin/env node

// Smoke checks for the standalone WordPress starter path used by emulsify-cli.
// The fixture mirrors the current CLI sequence: clone starter, write
// project.emulsify.json, npm install creates a lockfile, then .cli/init.js runs.

const childProcess = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const repoRoot = path.resolve(__dirname, '../..');
const starterRoot = path.join(repoRoot, 'whisk');
const excludedCopySegments = new Set([
  '.coverage',
  '.git',
  '.out',
  '.cache',
  'dist',
  'node_modules',
]);

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function writeJson(filePath, data) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, `${JSON.stringify(data, null, 2)}\n`);
}

function copyStarterFixture(source, destination) {
  fs.mkdirSync(destination, { recursive: true });

  for (const entry of fs.readdirSync(source, { withFileTypes: true })) {
    if (excludedCopySegments.has(entry.name)) {
      continue;
    }

    const sourcePath = path.join(source, entry.name);
    const destinationPath = path.join(destination, entry.name);

    if (entry.isDirectory()) {
      copyStarterFixture(sourcePath, destinationPath);
      continue;
    }

    if (entry.isFile()) {
      fs.mkdirSync(path.dirname(destinationPath), { recursive: true });
      fs.copyFileSync(sourcePath, destinationPath);
    }
  }
}

function removePath(filePath) {
  if (fs.existsSync(filePath)) {
    fs.rmSync(filePath, { force: true, recursive: true });
  }
}

function parseThemeHeader(contents) {
  return Object.fromEntries(
    contents
      .split(/\r?\n/)
      .map((line) => line.match(/^\s*\*\s*([^:]+):\s*(.*?)\s*$/))
      .filter(Boolean)
      .map((match) => [match[1].trim(), match[2].trim()]),
  );
}

function runNodeScript(scriptPath) {
  const result = childProcess.spawnSync(process.execPath, [scriptPath], {
    cwd: path.dirname(scriptPath),
    encoding: 'utf8',
    env: process.env,
  });

  if (result.status !== 0) {
    throw new Error(
      `${scriptPath} failed:\n${result.stdout || ''}${result.stderr || ''}`.trim(),
    );
  }
}

function runWhiskTestScript() {
  const jestBin = path.join(
    starterRoot,
    'node_modules',
    '.bin',
    process.platform === 'win32' ? 'jest.cmd' : 'jest',
  );

  assert(
    fs.existsSync(jestBin),
    'Whisk dependencies are not installed. Run `npm run whisk:install` before the starter init smoke test.',
  );

  const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
  const result = childProcess.spawnSync(npm, ['--prefix', 'whisk', 'run', 'test'], {
    cwd: repoRoot,
    encoding: 'utf8',
    env: process.env,
  });

  if (result.status !== 0) {
    throw new Error(
      `npm --prefix whisk run test failed:\n${result.stdout || ''}${result.stderr || ''}`.trim(),
    );
  }
}

const workRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-wordpress-starter-'));
const target = path.join(workRoot, 'acme-theme');
const hostileName = 'Acme */ echo 1; /* Theme';

try {
  copyStarterFixture(starterRoot, target);

  writeJson(path.join(target, 'project.emulsify.json'), {
    project: {
      platform: 'wordpress',
      name: hostileName,
      machineName: 'acme-theme',
    },
    starter: {
      repository: 'https://github.com/emulsify-ds/emulsify-wordpress-starter',
    },
  });

  writeJson(path.join(target, 'patterns/smoke-pattern.json'), {
    name: 'whisk/smoke-pattern',
    title: 'Smoke Pattern',
    categories: ['text'],
    content: '<!-- wp:paragraph --><p>Smoke pattern content</p><!-- /wp:paragraph -->',
  });

  writeJson(path.join(target, 'package-lock.json'), {
    name: 'whisk',
    version: '2.0.0',
    lockfileVersion: 3,
    requires: true,
    packages: {
      '': {
        name: 'whisk',
        version: '2.0.0',
        dependencies: {
          '@emulsify/core': '^4.2.0',
        },
      },
    },
  });

  runNodeScript(path.join(target, '.cli/init.js'));

  const style = parseThemeHeader(fs.readFileSync(path.join(target, 'style.css'), 'utf8'));
  const packageJson = readJson(path.join(target, 'package.json'));
  const packageLock = readJson(path.join(target, 'package-lock.json'));
  const project = readJson(path.join(target, 'project.emulsify.json'));
  const pattern = readJson(path.join(target, 'patterns/smoke-pattern.json'));
  const functionsPhp = fs.readFileSync(path.join(target, 'functions.php'), 'utf8');
  const pageTwig = fs.readFileSync(path.join(target, 'templates/page.twig'), 'utf8');

  assert(style['Theme Name'] === 'Acme echo 1 Theme', 'style.css should use a source-safe Theme Name.');
  assert(!style['Theme Name'].includes('*/'), 'style.css should not contain a crafted comment terminator.');
  assert(style['Text Domain'] === 'acme-theme', 'style.css should update Text Domain.');
  assert(style.Template === 'emulsify', 'style.css should keep Template: emulsify.');
  assert(packageJson.name === 'acme-theme', 'package.json should update name.');
  assert(packageLock.name === 'acme-theme', 'package-lock.json should update the root name.');
  assert(packageLock.packages[''].name === 'acme-theme', 'package-lock.json packages[""].name should update.');
  assert(project.project.platform === 'wordpress', 'project.emulsify.json should keep project.platform: wordpress.');
  assert(project.project.name === hostileName, 'project.emulsify.json should preserve the richer project.name.');
  assert(project.project.machineName === 'acme-theme', 'project.emulsify.json should update project.machineName.');
  assert(project.project.generatedFrom === 'emulsify-wordpress', 'project.emulsify.json should identify the generated child theme source.');
  assert(project.project.generatedFromVersion === '2.0.0', 'project.emulsify.json should record the generated child theme source version.');
  assert(
    project.starter.repository === 'https://github.com/emulsify-ds/emulsify-wordpress-starter',
    'project.emulsify.json should keep the standalone starter repository.',
  );
  assert(functionsPhp.includes('Acme echo 1 Theme child theme hooks.'), 'functions.php should use a source-safe visible label.');
  assert(!functionsPhp.includes('*/ echo 1; /*'), 'functions.php should not contain the attempted docblock breakout.');
  assert(pageTwig.includes('acme-theme-page'), 'templates/page.twig should update the page class.');
  assert(!pageTwig.includes('whisk-page'), 'templates/page.twig should not keep the starter page class.');
  assert(pattern.name === 'acme-theme/smoke-pattern', 'JSON pattern names should update the whisk namespace.');
  assert(fs.existsSync(path.join(target, '.cli/init.js')), 'Starter should include the emulsify-cli init hook.');
  assert(fs.existsSync(path.join(target, '.gitignore')), 'Starter should include standalone ignore rules.');
  assert(fs.existsSync(path.join(target, '.nvmrc')), 'Starter should use .nvmrc for Node tooling.');
  assert(!fs.existsSync(path.join(target, '.nvm')), 'Starter should not keep the legacy .nvm file.');

  for (const generatedPath of ['node_modules', 'dist', '.out', '.coverage', '.cache', '.git']) {
    assert(
      !fs.existsSync(path.join(target, generatedPath)),
      `Cloned starter fixture should not contain copied ${generatedPath} output.`,
    );
  }

  runWhiskTestScript();

  console.log('WordPress starter init smoke checks passed.');
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
} finally {
  removePath(workRoot);
}
