#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const childProcess = require('child_process');

const repoRoot = path.resolve(__dirname, '../..');
const results = [];

function addResult(status, name, detail) {
  results.push({ status, name, detail });
}

function readFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

function readJson(relativePath) {
  return JSON.parse(readFile(relativePath));
}

function ensure(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function semver(value) {
  return /^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(String(value));
}

function runStaticCheck(name, callback) {
  try {
    const detail = callback();
    addResult('PASS', name, detail);
  }
  catch (error) {
    addResult('FAIL', name, error.message);
  }
}

function runCommandCheck(name, command, args) {
  const result = childProcess.spawnSync(command, args, {
    cwd: repoRoot,
    encoding: 'utf8',
    env: process.env,
    maxBuffer: 1024 * 1024 * 20,
  });
  const output = `${result.stdout || ''}${result.stderr || ''}`.trim();

  if (result.status !== 0) {
    addResult('FAIL', name, output || `${command} ${args.join(' ')} failed with exit code ${result.status}.`);
    return;
  }

  if (output.includes('WORDPRESS_SMOKE_SKIPPED')) {
    addResult('SKIP', name, output.split(/\r?\n/).filter(Boolean).pop());
    return;
  }

  addResult('PASS', name, output.split(/\r?\n/).filter(Boolean).pop() || `${command} ${args.join(' ')} passed.`);
}

function listFilesRecursive(relativePath, predicate) {
  const absolutePath = path.join(repoRoot, relativePath);
  if (!fs.existsSync(absolutePath)) {
    return [];
  }

  const files = [];
  for (const entry of fs.readdirSync(absolutePath, { withFileTypes: true })) {
    const childPath = path.join(relativePath, entry.name);
    if (entry.isDirectory()) {
      if (childPath === '.git') {
        continue;
      }
      files.push(...listFilesRecursive(childPath, predicate));
    }
    else if (predicate(childPath)) {
      files.push(childPath);
    }
  }

  return files;
}

function extractJsonObjectSegment(text, key) {
  const keyPattern = new RegExp(`"${key}"\\s*:\\s*\\{`);
  const match = keyPattern.exec(text);
  if (!match) {
    return null;
  }

  const start = text.indexOf('{', match.index);
  let depth = 0;
  let inString = false;
  let escaped = false;

  for (let index = start; index < text.length; index += 1) {
    const character = text[index];

    if (inString) {
      if (escaped) {
        escaped = false;
      }
      else if (character === '\\') {
        escaped = true;
      }
      else if (character === '"') {
        inString = false;
      }
      continue;
    }

    if (character === '"') {
      inString = true;
      continue;
    }

    if (character === '{') {
      depth += 1;
      continue;
    }

    if (character === '}') {
      depth -= 1;
      if (depth === 0) {
        return text.slice(start, index + 1);
      }
    }
  }

  return null;
}

function findDuplicatePackageScripts(relativePath) {
  const raw = readFile(relativePath);
  const scriptsBlock = extractJsonObjectSegment(raw, 'scripts');
  ensure(scriptsBlock, `Unable to locate the scripts block in ${relativePath}.`);

  const keys = [...scriptsBlock.matchAll(/^\s*"([^"]+)"\s*:/gm)].map((match) => match[1]);
  const seen = new Set();
  const duplicates = new Set();

  for (const key of keys) {
    if (seen.has(key)) {
      duplicates.add(key);
    }
    seen.add(key);
  }

  return [...duplicates].sort();
}

function parseWordPressThemeHeader(relativePath) {
  const header = {};
  const contents = readFile(relativePath);

  for (const line of contents.split(/\r?\n/)) {
    const match = line.match(/^\s*\*\s*([^:]+):\s*(.*?)\s*$/);
    if (match) {
      header[match[1].trim()] = match[2].trim();
    }
  }

  return header;
}

function ensureNoTitleCaseBuildPhrase(label, value) {
  ensure(!/Webpack Build|Vite Build/.test(value), `${label} should not use title-case build workflow phrases.`);
}

function ensureWordPressLanguage(label, value) {
  ensure(value.includes('WordPress'), `${label} should use the canonical WordPress spelling.`);
  ensure(!/Wordpress/.test(value), `${label} should not use Wordpress.`);
}

function ensureViteLanguage(label, value) {
  ensure(value.includes('Vite-based build workflow'), `${label} should mention the Vite-based build workflow.`);
  ensure(value.includes('Emulsify Core 4'), `${label} should mention Emulsify Core 4.`);
  ensure(!/Webpack/i.test(value), `${label} should not mention Webpack.`);
}

function getReleasePluginOptions(releaseConfig, pluginName) {
  const plugin = releaseConfig.plugins.find((candidate) => {
    if (Array.isArray(candidate)) {
      return candidate[0] === pluginName;
    }

    return candidate === pluginName;
  });

  ensure(plugin, `release.config.js must include ${pluginName}.`);
  return Array.isArray(plugin) ? plugin[1] || {} : {};
}

function ensureBreakingParser(label, parserOpts) {
  ensure(parserOpts, `${label} parserOpts are required.`);
  ensure(parserOpts.breakingHeaderPattern, `${label} must define breakingHeaderPattern.`);
  ensure(parserOpts.breakingHeaderPattern.test('feat!: remove legacy API'), `${label} should parse feat!: as breaking.`);
  ensure(parserOpts.breakingHeaderPattern.test('feat(theme)!: remove legacy API'), `${label} should parse feat(scope)!: as breaking.`);
  ensure(parserOpts.noteKeywords.includes('BREAKING CHANGE'), `${label} should parse BREAKING CHANGE notes.`);
  ensure(parserOpts.noteKeywords.includes('BREAKING CHANGES'), `${label} should parse BREAKING CHANGES notes.`);
}

function runStaticChecks() {
  const rootPackage = readJson('package.json');
  const whiskPackage = readJson('whisk/package.json');
  const composer = readJson('composer.json');
  const rootThemeHeader = parseWordPressThemeHeader('style.css');
  const whiskThemeHeader = parseWordPressThemeHeader('whisk/style.css');
  const whiskProject = readJson('whisk/project.emulsify.json');
  const releaseConfig = require(path.join(repoRoot, 'release.config.js'));
  const semanticReleaseWorkflow = readFile('.github/workflows/semantic-release.yml');

  runStaticCheck('Required files', () => {
    const requiredFiles = [
      '.github/scripts/release-check.cjs',
      '.github/workflows/semantic-release.yml',
      '.gitignore',
      '.nvmrc',
      'README.md',
      'composer.json',
      'functions.php',
      'includes/class-attribute-bag.php',
      'includes/class-twig.php',
      'package.json',
      'release.config.js',
      'style.css',
      '.github/scripts/attribute-helper-smoke.php',
      '.github/scripts/wordpress-fixture-smoke.cjs',
      'whisk/functions.php',
      'whisk/package.json',
      'whisk/project.emulsify.json',
      'whisk/style.css',
    ];
    const missingFiles = requiredFiles.filter((file) => !fs.existsSync(path.join(repoRoot, file)));
    ensure(missingFiles.length === 0, `Missing required files: ${missingFiles.join(', ')}.`);
    return `Found ${requiredFiles.length} required release files.`;
  });

  runStaticCheck('Root release metadata', () => {
    ensure(rootPackage.name === 'emulsify-wordpress-theme', 'package.json name should be emulsify-wordpress-theme.');
    ensure(semver(rootPackage.version), 'package.json version must be a valid semver string.');
    ensure(rootPackage.description, 'package.json description is required.');
    ensureWordPressLanguage('package.json description', rootPackage.description);
    ensureViteLanguage('package.json description', rootPackage.description);
    ensureNoTitleCaseBuildPhrase('package.json description', rootPackage.description);
    ensure(rootPackage.license === 'GPL-2.0-only', 'package.json license should be GPL-2.0-only.');
    ensure(rootPackage.engines && rootPackage.engines.node === '>=24.10', 'package.json engines.node should be >=24.10.');
    ensure(rootPackage.repository.url === 'git+https://github.com/emulsify-ds/emulsify-wordpress-theme.git', 'package.json repository.url should target emulsify-wordpress-theme.');
    ensure(rootPackage.bugs.url === 'https://github.com/emulsify-ds/emulsify-wordpress-theme/issues', 'package.json bugs.url should target emulsify-wordpress-theme.');
    ensure(rootPackage.scripts['release:check'] === 'node .github/scripts/release-check.cjs', 'package.json should expose npm run release:check.');
    ensure(rootPackage.devDependencies['@semantic-release/npm'], 'package.json should declare @semantic-release/npm directly.');
    ensure(composer.name === 'emulsify-ds/emulsify-wordpress-theme', 'composer.json name should be emulsify-ds/emulsify-wordpress-theme.');
    ensure(composer.type === 'wordpress-theme', 'composer.json type should be wordpress-theme.');
    ensure(composer.license === 'GPL-2.0-only', 'composer.json license should be GPL-2.0-only.');
    ensure(composer.homepage === 'https://www.emulsify.info', 'composer.json homepage should use the canonical HTTPS URL.');
    ensureWordPressLanguage('composer.json description', composer.description);
    ensureViteLanguage('composer.json description', composer.description);
    ensure(composer.description.includes('child themes'), 'composer.json description should mention child themes.');
    return `Validated root package ${rootPackage.version} and composer metadata.`;
  });

  runStaticCheck('WordPress theme headers', () => {
    ensure(rootThemeHeader['Theme Name'] === 'Emulsify', 'style.css Theme Name should be Emulsify.');
    ensure(rootThemeHeader['Text Domain'] === 'emulsify', 'style.css Text Domain should be emulsify.');
    ensure(rootThemeHeader.Version === rootPackage.version, 'style.css Version should match package.json version.');
    ensure(rootThemeHeader['Requires at least'] === '6.7', 'style.css Requires at least should stay aligned to the WordPress baseline.');
    ensure(rootThemeHeader['Tested up to'] === '6.7', 'style.css Tested up to should stay aligned to the WordPress baseline.');
    ensure(rootThemeHeader['Requires PHP'] === '8.3', 'style.css Requires PHP should stay aligned to the release baseline.');
    ensureViteLanguage('style.css Description', rootThemeHeader.Description);
    ensure(whiskThemeHeader['Theme Name'] === 'Whisk', 'whisk/style.css Theme Name should be Whisk.');
    ensure(whiskThemeHeader.Template === 'emulsify', 'whisk/style.css Template should be emulsify.');
    ensure(whiskThemeHeader['Text Domain'] === 'whisk', 'whisk/style.css Text Domain should be whisk.');
    ensure(whiskThemeHeader.Version === whiskPackage.version, 'whisk/style.css Version should match whisk/package.json version.');
    ensure(whiskThemeHeader.Description.includes('child theme'), 'whisk/style.css Description should use child theme language.');
    ensureViteLanguage('whisk/style.css Description', whiskThemeHeader.Description);
    return 'Parent and Whisk WordPress theme headers are coherent with package metadata.';
  });

  runStaticCheck('Timber attribute helpers', () => {
    const bootstrap = readFile('includes/class-bootstrap.php');
    const twig = readFile('includes/class-twig.php');
    const attributeBag = readFile('includes/class-attribute-bag.php');
    const smoke = readFile('.github/scripts/attribute-helper-smoke.php');

    ensure(bootstrap.includes('class-attribute-bag.php'), 'Bootstrap should load AttributeBag before Twig helpers.');
    ensure(attributeBag.includes('implements \\Stringable'), 'AttributeBag should serialize safely in Twig string contexts.');
    ensure(attributeBag.includes('function addClass'), 'AttributeBag should support Core-style class merging.');
    ensure(attributeBag.includes('function toString'), 'AttributeBag should expose explicit serialization.');
    ensure(twig.includes("'needs_context' => true"), 'Timber helper functions should accept Twig context.');
    ensure(twig.includes('new AttributeBag'), 'Twig helpers should return AttributeBag objects.');
    ensure(smoke.includes('{{ bem("button", ["primary"]) }}'), 'Attribute helper smoke script should render a bem() Twig fixture.');
    ensure(smoke.includes('{{ add_attributes({ class: ["foo"] }) }}'), 'Attribute helper smoke script should render an add_attributes() Twig fixture.');
    return 'Attribute helper runtime and smoke fixture are wired.';
  });

  runStaticCheck('Whisk Core 4 and Vite metadata', () => {
    const scripts = whiskPackage.scripts || {};
    const scriptText = Object.values(scripts).join('\n');
    ensure(whiskPackage.name === 'whisk', 'whisk/package.json name should remain whisk.');
    ensure(semver(whiskPackage.version), 'whisk/package.json version must be a valid semver string.');
    ensure(whiskPackage.description, 'whisk/package.json description is required.');
    ensureViteLanguage('whisk/package.json description', whiskPackage.description);
    ensure(whiskPackage.license === 'GPL-2.0-only', 'whisk/package.json license should align with the WordPress theme.');
    ensure(whiskPackage.engines && whiskPackage.engines.node === '>=24', 'whisk/package.json engines.node should be >=24.');
    ensure(whiskPackage.type === 'module', 'whisk/package.json should remain an ES module package.');
    ensure(whiskProject.project.platform === 'none', 'whisk/project.emulsify.json should use platform "none" until Core ships a WordPress adapter.');
    ensure(whiskProject.project.name === 'whisk', 'whisk/project.emulsify.json project.name should remain whisk.');
    ensure(whiskProject.project.machineName === 'whisk', 'whisk/project.emulsify.json project.machineName should remain whisk.');
    ensure(fs.existsSync(path.join(repoRoot, 'whisk/src/components')), 'whisk/src/components should be the primary component source.');
    ensure(!fs.existsSync(path.join(repoRoot, 'whisk/components')), 'whisk/components should not remain as an unused starter placeholder.');
    for (const entryFile of ['foundation.scss', 'layout.scss', 'tokens.scss']) {
      ensure(fs.existsSync(path.join(repoRoot, 'whisk/src', entryFile)), `whisk/src/${entryFile} should exist for Core 4 Vite global entries.`);
    }
    ensure(whiskPackage.dependencies && whiskPackage.dependencies['@emulsify/core'], 'whisk/package.json must declare @emulsify/core.');
    ensure(whiskPackage.dependencies['@emulsify/core'] === '^4.1.0', 'whisk/package.json should target Emulsify Core ^4.1.0.');
    ensure(scripts.build && scripts.build.includes('vite build --config node_modules/@emulsify/core/config/vite/vite.config.js'), 'whisk/package.json build script should use the Emulsify Core Vite config.');
    ensure(scripts.vite && scripts.vite.includes('vite build --watch'), 'whisk/package.json should expose a Vite watch script.');
    ensure(scripts.develop && scripts.develop.includes('npm:vite'), 'whisk/package.json develop script should run the Vite watcher.');
    ensure(!scripts.webpack, 'whisk/package.json should not expose a webpack script.');
    ensure(!scripts['build-dev'], 'whisk/package.json should not expose the old Webpack build-dev script.');
    ensure(!scripts['tokens:transform'], 'whisk/package.json should not ship a default token-transformer script.');
    ensure(!scripts['tokens:build'], 'whisk/package.json should not ship a default token build script.');
    ensure(!scripts['style-dictionary:build'], 'whisk/package.json should not ship a default Style Dictionary script.');
    ensure(!/\bwebpack\b/i.test(scriptText), 'whisk/package.json scripts should not reference Webpack.');
    ensure(!/\btoken-transformer\b/.test(scriptText), 'whisk/package.json scripts should not reference token-transformer.');
    ensure(!/\bstyle-dictionary\b/.test(scriptText), 'whisk/package.json scripts should not reference Style Dictionary.');
    ensure(!String(whiskPackage.dependencies['@emulsify/core']).startsWith('^3.'), 'whisk/package.json should not target Emulsify Core 3.');
    return `Whisk targets ${whiskPackage.dependencies['@emulsify/core']} with Vite scripts.`;
  });

  runStaticCheck('Starter asset placeholders', () => {
    const iconFiles = listFilesRecursive('whisk/assets/icons', (file) => path.basename(file) !== '.gitkeep');
    const expectedPlaceholders = [
      'whisk/assets/fonts/.gitkeep',
      'whisk/assets/icons/.gitkeep',
      'whisk/assets/images/.gitkeep',
    ];

    ensure(iconFiles.length === 0, `Remove client-specific starter icons: ${iconFiles.join(', ')}.`);
    for (const placeholder of expectedPlaceholders) {
      ensure(fs.existsSync(path.join(repoRoot, placeholder)), `${placeholder} should keep the starter asset directory.`);
    }
    ensure(!fs.existsSync(path.join(repoRoot, 'whisk/assets/audio')), 'whisk/assets/audio should not ship as a default starter directory.');
    ensure(!fs.existsSync(path.join(repoRoot, 'whisk/assets/video')), 'whisk/assets/video should not ship as a default starter directory.');
    return 'Whisk keeps only generic starter asset placeholders.';
  });

  runStaticCheck('Duplicate package scripts', () => {
    const duplicates = [
      ...findDuplicatePackageScripts('package.json').map((key) => `package.json:${key}`),
      ...findDuplicatePackageScripts('whisk/package.json').map((key) => `whisk/package.json:${key}`),
    ];
    ensure(duplicates.length === 0, `Duplicate script keys found: ${duplicates.join(', ')}.`);
    return 'No duplicate script keys were found in package metadata.';
  });

  runStaticCheck('Semantic release configuration', () => {
    const analyzerOptions = getReleasePluginOptions(releaseConfig, '@semantic-release/commit-analyzer');
    const notesOptions = getReleasePluginOptions(releaseConfig, '@semantic-release/release-notes-generator');
    ensure(releaseConfig.tagFormat === '${version}', 'release.config.js should emit non-prefixed semver tags.');
    ensure(Array.isArray(releaseConfig.branches), 'release.config.js branches must be an array.');
    ensure(releaseConfig.branches.length === 1 && releaseConfig.branches[0] === 'main', 'release.config.js should publish only from main.');
    ensureBreakingParser('@semantic-release/commit-analyzer', analyzerOptions.parserOpts);
    ensureBreakingParser('@semantic-release/release-notes-generator', notesOptions.parserOpts);
    ensure(semanticReleaseWorkflow.includes('release-readiness:'), 'semantic-release.yml should run release-readiness before publishing.');
    ensure(semanticReleaseWorkflow.includes('needs: release-readiness'), 'semantic-release.yml release job should wait for release readiness.');
    ensure(semanticReleaseWorkflow.includes('contents: write'), 'semantic-release.yml should grant GitHub release permissions explicitly.');
    ensure(semanticReleaseWorkflow.includes('id: semantic'), 'semantic-release.yml should expose the semantic-release step as steps.semantic.');
    ensure(semanticReleaseWorkflow.includes('npm run release:check'), 'semantic-release.yml should run local release readiness checks.');
    ensure(semanticReleaseWorkflow.includes('wp-cli'), 'semantic-release.yml should install WP-CLI for the WordPress smoke fixture.');
    ensure(semanticReleaseWorkflow.includes('mysql:'), 'semantic-release.yml should provide a MySQL service for the WordPress smoke fixture.');
    ensure(semanticReleaseWorkflow.includes('WP_SMOKE_DB_HOST'), 'semantic-release.yml should pass WordPress smoke database settings.');
    return 'Semantic release is configured for non-prefixed tags and breaking-change major releases.';
  });

  runStaticCheck('No Drupal.org workflow references', () => {
    const forbiddenPatterns = [
      /Drupal\.org/i,
      /drupal-org/i,
      /DRUPAL_ORG/,
      /DRUPAL_REPO_URL/,
      /shimataro\/ssh-key-action/,
      /SSH_CONFIG/,
      /KNOWN_HOSTS/,
    ];
    const files = listFilesRecursive('.github/workflows', (file) => /\.ya?ml$/.test(file));
    const violations = [];

    for (const file of files) {
      const contents = readFile(file);
      for (const pattern of forbiddenPatterns) {
        if (pattern.test(contents)) {
          violations.push(`${file} matches ${pattern}`);
        }
      }
    }

    ensure(violations.length === 0, `Remove Drupal.org workflow references:\n${violations.join('\n')}`);
    return 'No Drupal.org sync workflow references or stale Drupal.org secrets remain.';
  });

  runStaticCheck('No .DS_Store files', () => {
    const dsStoreFiles = listFilesRecursive('.', (file) => path.basename(file) === '.DS_Store')
      .filter((file) => !file.startsWith('.git/'));
    ensure(dsStoreFiles.length === 0, `.DS_Store files found outside .git: ${dsStoreFiles.join(', ')}.`);
    return 'No .DS_Store files were found outside .git.';
  });

  runCommandCheck('WordPress fixture smoke', process.execPath, ['.github/scripts/wordpress-fixture-smoke.cjs']);
}

function printSummary() {
  console.log('\nRelease Check Summary');
  for (const result of results) {
    console.log(`${result.status.padEnd(4)} ${result.name}: ${result.detail}`);
  }
}

runStaticChecks();
printSummary();

const hasBlockingFailure = results.some((result) => result.status === 'FAIL');
process.exit(hasBlockingFailure ? 1 : 0);
