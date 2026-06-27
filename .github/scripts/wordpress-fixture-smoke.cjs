#!/usr/bin/env node

const childProcess = require('child_process');
const fs = require('fs');
const net = require('net');
const os = require('os');
const path = require('path');

const repoRoot = path.resolve(__dirname, '../..');
const required = process.env.CI === 'true' || process.env.WP_SMOKE_REQUIRED === '1';
const keepFixture = process.env.WP_SMOKE_KEEP === '1';
const host = process.env.WP_SMOKE_HOST || '127.0.0.1';
const wordpressVersion = process.env.WP_SMOKE_WORDPRESS_VERSION || '6.7';
let workDir = null;
let logDir = null;
let server = null;
let databaseName = null;
let currentDatabaseEnv = null;

function log(message) {
  console.log(`[wordpress-smoke] ${message}`);
}

function unavailable(message) {
  if (required) {
    throw new Error(message);
  }

  console.log(`WORDPRESS_SMOKE_SKIPPED ${message}`);
  process.exit(0);
}

function commandExists(command) {
  const result = childProcess.spawnSync('sh', ['-lc', `command -v ${command}`], {
    encoding: 'utf8',
  });

  return result.status === 0;
}

function appendLog(file, contents) {
  if (!logDir || !contents) {
    return;
  }

  fs.appendFileSync(path.join(logDir, file), contents);
}

function run(label, command, args, options = {}) {
  appendLog('commands.log', `\n$ ${[command, ...args].join(' ')}\n`);

  const result = childProcess.spawnSync(command, args, {
    cwd: options.cwd || repoRoot,
    encoding: 'utf8',
    env: options.env || process.env,
    input: options.input,
    maxBuffer: 1024 * 1024 * 20,
  });

  appendLog('commands.log', result.stdout);
  appendLog('commands.log', result.stderr);

  if (result.status !== 0) {
    throw new Error(`${label} failed with exit code ${result.status}. See ${path.join(logDir, 'commands.log')}.`);
  }

  return result.stdout.trim();
}

function wp(wpPath, args, options = {}) {
  return run(`wp ${args.join(' ')}`, 'wp', ['--allow-root', `--path=${wpPath}`, ...args], options);
}

function ensureTools() {
  for (const command of ['php', 'composer', 'npm', 'wp']) {
    if (!commandExists(command)) {
      unavailable(`Required command "${command}" is not available.`);
    }
  }

  const mysqli = childProcess.spawnSync('php', ['-r', 'exit(extension_loaded("mysqli") ? 0 : 1);']);
  if (mysqli.status !== 0) {
    unavailable('The PHP mysqli extension is required for the WordPress fixture database.');
  }

  if (!required && !process.env.WP_SMOKE_DB_HOST && !process.env.WP_SMOKE_DB_NAME) {
    unavailable('Database environment is not configured. Set WP_SMOKE_DB_HOST, WP_SMOKE_DB_NAME, WP_SMOKE_DB_USER, and WP_SMOKE_DB_PASSWORD to run locally.');
  }
}

function databaseEnv() {
  const uniqueName = `emulsify_smoke_${process.pid}_${Date.now()}`;
  databaseName = process.env.WP_SMOKE_DB_NAME || uniqueName;

  if (!/^[A-Za-z0-9_]+$/.test(databaseName)) {
    throw new Error('WP_SMOKE_DB_NAME may only contain letters, numbers, and underscores.');
  }

  return {
    ...process.env,
    WP_SMOKE_DB_HOST: process.env.WP_SMOKE_DB_HOST || '127.0.0.1',
    WP_SMOKE_DB_PORT: process.env.WP_SMOKE_DB_PORT || '3306',
    WP_SMOKE_DB_NAME: databaseName,
    WP_SMOKE_DB_USER: process.env.WP_SMOKE_DB_USER || 'root',
    WP_SMOKE_DB_PASSWORD: process.env.WP_SMOKE_DB_PASSWORD || 'root',
  };
}

function createDatabase(env) {
  const code = String.raw`
$host = getenv('WP_SMOKE_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('WP_SMOKE_DB_PORT') ?: 3306);
$user = getenv('WP_SMOKE_DB_USER') ?: 'root';
$pass = getenv('WP_SMOKE_DB_PASSWORD') ?: '';
$db = getenv('WP_SMOKE_DB_NAME') ?: 'wordpress_smoke';
if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) {
  fwrite(STDERR, "Unsafe database name.\n");
  exit(1);
}
$mysqli = mysqli_init();
if (!$mysqli || !$mysqli->real_connect($host, $user, $pass, null, $port)) {
  fwrite(STDERR, "Unable to connect to MySQL: " . mysqli_connect_error() . "\n");
  exit(1);
}
$mysqli->query('DROP DATABASE IF EXISTS ' . $db);
if (!$mysqli->query('CREATE DATABASE ' . $db . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
  fwrite(STDERR, "Unable to create database: " . $mysqli->error . "\n");
  exit(1);
}
`;

  run('Create WordPress smoke database', 'php', ['-r', code], { env });
}

function dropDatabase(env) {
  if (!databaseName) {
    return;
  }

  const code = String.raw`
$host = getenv('WP_SMOKE_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('WP_SMOKE_DB_PORT') ?: 3306);
$user = getenv('WP_SMOKE_DB_USER') ?: 'root';
$pass = getenv('WP_SMOKE_DB_PASSWORD') ?: '';
$db = getenv('WP_SMOKE_DB_NAME') ?: '';
if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) {
  exit(0);
}
$mysqli = mysqli_init();
if ($mysqli && $mysqli->real_connect($host, $user, $pass, null, $port)) {
  $mysqli->query('DROP DATABASE IF EXISTS ' . $db);
}
`;

  try {
    run('Drop WordPress smoke database', 'php', ['-r', code], { env });
  }
  catch (error) {
    appendLog('commands.log', `Database cleanup failed: ${error.message}\n`);
  }
}

function stopServer() {
  if (!server) {
    return;
  }

  server.kill();
  server = null;
}

function copyDirectory(source, destination, filter) {
  fs.cpSync(source, destination, {
    recursive: true,
    filter: (sourcePath) => filter(path.relative(source, sourcePath).split(path.sep)),
  });
}

function copyThemes(themesDir) {
  const parentTheme = path.join(themesDir, 'emulsify');
  const childTheme = path.join(themesDir, 'whisk');

  copyDirectory(repoRoot, parentTheme, (segments) => {
    const first = segments[0];

    return ![
      '.git',
      '.github',
      '.coverage',
      '.out',
      '.publish',
      'node_modules',
      'vendor',
      'whisk',
    ].includes(first);
  });

  copyDirectory(path.join(repoRoot, 'whisk'), childTheme, (segments) => {
    const first = segments[0];

    return ![
      '.coverage',
      '.out',
      'node_modules',
    ].includes(first);
  });

  return { childTheme, parentTheme };
}

function ensureWhiskBuild() {
  if (process.env.WP_SMOKE_BUILD_WHISK === '0') {
    return;
  }

  const whiskDir = path.join(repoRoot, 'whisk');
  const coreDependency = path.join(whiskDir, 'node_modules', '@emulsify', 'core');

  if (!fs.existsSync(coreDependency)) {
    run('Install Whisk dependencies', 'npm', ['install', '--ignore-scripts', '--package-lock=false'], {
      cwd: whiskDir,
    });
  }

  run('Build Whisk child theme assets', 'npm', ['run', 'build'], { cwd: whiskDir });

  for (const file of ['foundation.css', 'layout.css', 'tokens.css']) {
    const builtFile = path.join(whiskDir, 'dist', 'global', file);
    if (!fs.existsSync(builtFile)) {
      throw new Error(`Expected built Whisk global CSS is missing: ${builtFile}`);
    }
  }
}

function installThemeDependencies(parentTheme) {
  run('Install Timber with Composer', 'composer', ['install', '--no-interaction', '--no-progress', '--prefer-dist', '--no-dev'], {
    cwd: parentTheme,
  });
}

function configureDebugLog(wpPath) {
  const configPath = path.join(wpPath, 'wp-config.php');
  const config = fs.readFileSync(configPath, 'utf8');
  const debugConfig = `
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
@ini_set( 'display_errors', 0 );
`;

  fs.writeFileSync(
    configPath,
    config.replace(/\/\* That's all, stop editing!.*$/s, `${debugConfig}\n$&`)
  );
}

function createFixtureContent(wpPath) {
  const pageId = wp(wpPath, [
    'post',
    'create',
    '--post_type=page',
    '--post_status=publish',
    '--post_title=Smoke Page',
    '--post_name=smoke-page',
    '--post_content=Smoke page content',
    '--porcelain',
  ]);
  const postId = wp(wpPath, [
    'post',
    'create',
    '--post_type=post',
    '--post_status=publish',
    '--post_title=Smoke Post',
    '--post_name=smoke-post',
    '--post_content=Smoke post content',
    '--porcelain',
  ]);
  const authorId = wp(wpPath, [
    'user',
    'create',
    'smokeauthor',
    'smokeauthor@example.test',
    '--role=author',
    '--display_name=Smoke Author',
    '--porcelain',
  ]);
  const categoryId = wp(wpPath, [
    'term',
    'create',
    'category',
    'Smoke Category',
    '--slug=smoke-category',
    '--porcelain',
  ]);

  wp(wpPath, ['post', 'update', postId, `--post_author=${authorId}`]);
  wp(wpPath, ['post', 'term', 'add', postId, 'category', categoryId]);
  wp(wpPath, [
    'comment',
    'create',
    `--comment_post_ID=${postId}`,
    '--comment_content=Smoke comment content',
    '--comment_author=Smoke Commenter',
    '--comment_author_email=commenter@example.test',
    '--comment_approved=1',
  ]);

  return { authorId, categoryId, pageId, postId };
}

function getAvailablePort() {
  return new Promise((resolve, reject) => {
    const probe = net.createServer();

    probe.once('error', reject);
    probe.listen(0, host, () => {
      const address = probe.address();
      probe.close(() => resolve(address.port));
    });
  });
}

function startServer(wpPath, port) {
  server = childProcess.spawn('php', ['-S', `${host}:${port}`, '-t', wpPath], {
    cwd: wpPath,
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  server.stdout.on('data', (data) => appendLog('server.log', data.toString()));
  server.stderr.on('data', (data) => appendLog('server.log', data.toString()));
  server.on('exit', (code, signal) => {
    appendLog('server.log', `\nPHP server exited with code ${code} signal ${signal}\n`);
  });
}

async function waitForServer(baseUrl) {
  const deadline = Date.now() + 30000;
  let lastError = null;

  while (Date.now() < deadline) {
    try {
      const response = await fetch(baseUrl);
      if (response.status < 500) {
        return;
      }
    }
    catch (error) {
      lastError = error;
    }

    await new Promise((resolve) => setTimeout(resolve, 500));
  }

  throw new Error(`WordPress fixture server did not become ready. ${lastError ? lastError.message : ''}`);
}

async function checkRoute(baseUrl, route) {
  const response = await fetch(`${baseUrl}${route.path}`);
  const body = await response.text();
  const expectedStatus = route.status || 200;

  appendLog('http.log', `\n${route.name} ${route.path}\nStatus: ${response.status}\n`);
  appendLog('http.log', body.slice(0, 4000));
  appendLog('http.log', '\n');

  if (response.status !== expectedStatus) {
    throw new Error(`${route.name} returned ${response.status}; expected ${expectedStatus}.`);
  }

  for (const text of route.includes) {
    if (!body.includes(text)) {
      throw new Error(`${route.name} did not include expected text: ${text}`);
    }
  }
}

async function renderRoutes(wpPath, content, baseUrl, port) {
  startServer(wpPath, port);
  await waitForServer(baseUrl);

  const routes = [
    { name: 'home', path: '/', includes: ['Emulsify Smoke', 'Smoke Post'] },
    { name: 'page', path: `/?page_id=${content.pageId}`, includes: ['Smoke Page', 'Smoke page content'] },
    { name: 'single', path: `/?p=${content.postId}`, includes: ['Smoke Post', 'Smoke post content', 'Smoke comment content'] },
    { name: 'archive', path: `/?cat=${content.categoryId}`, includes: ['Smoke Category', 'Smoke Post'] },
    { name: 'search', path: '/?s=Smoke', includes: ['Search results for Smoke', 'Smoke Post'] },
    { name: 'author', path: `/?author=${content.authorId}`, includes: ['Archive of Smoke Author', 'Smoke Post'] },
    { name: '404', path: '/?p=9999999', status: 404, includes: ['Page not found'] },
  ];

  for (const route of routes) {
    await checkRoute(baseUrl, route);
  }
}

function dumpLogs(wpPath) {
  if (!logDir) {
    return;
  }

  const files = [
    path.join(logDir, 'commands.log'),
    path.join(logDir, 'server.log'),
    path.join(logDir, 'http.log'),
    path.join(wpPath || '', 'wp-content', 'debug.log'),
  ];

  console.error('\nWordPress Smoke Logs');
  for (const file of files) {
    if (!file || !fs.existsSync(file)) {
      continue;
    }

    const contents = fs.readFileSync(file, 'utf8');
    const tail = contents.slice(-12000);
    console.error(`\n--- ${file} ---\n${tail}`);
  }
}

async function main() {
  if (process.env.WP_SMOKE_SKIP === '1') {
    unavailable('WP_SMOKE_SKIP=1');
  }

  ensureTools();

  workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-wordpress-smoke-'));
  logDir = path.join(workDir, 'logs');
  fs.mkdirSync(logDir, { recursive: true });

  const wpPath = path.join(workDir, 'wordpress');
  const env = databaseEnv();
  currentDatabaseEnv = env;
  const port = Number(process.env.WP_SMOKE_PORT) || await getAvailablePort();
  const baseUrl = `http://${host}:${port}`;

  log(`Fixture directory: ${workDir}`);
  ensureWhiskBuild();
  createDatabase(env);

  run('Download WordPress core', 'wp', [
    '--allow-root',
    `--path=${wpPath}`,
    'core',
    'download',
    `--version=${wordpressVersion}`,
    '--force',
  ]);

  run('Create wp-config.php', 'wp', [
    '--allow-root',
    `--path=${wpPath}`,
    'config',
    'create',
    `--dbname=${env.WP_SMOKE_DB_NAME}`,
    `--dbuser=${env.WP_SMOKE_DB_USER}`,
    `--dbpass=${env.WP_SMOKE_DB_PASSWORD}`,
    `--dbhost=${env.WP_SMOKE_DB_HOST}:${env.WP_SMOKE_DB_PORT}`,
    '--skip-check',
  ]);
  configureDebugLog(wpPath);

  const themesDir = path.join(wpPath, 'wp-content', 'themes');
  const { parentTheme } = copyThemes(themesDir);
  installThemeDependencies(parentTheme);

  wp(wpPath, [
    'core',
    'install',
    `--url=${baseUrl}`,
    '--title=Emulsify Smoke',
    '--admin_user=admin',
    '--admin_password=admin-password-123',
    '--admin_email=admin@example.test',
  ]);
  wp(wpPath, ['theme', 'activate', 'whisk']);
  wp(wpPath, [
    'eval',
    'if ( ! class_exists( "\\\\Timber\\\\Timber" ) ) { fwrite( STDERR, "Timber is not loaded.\\n" ); exit( 1 ); }',
  ]);

  const content = createFixtureContent(wpPath);
  await renderRoutes(wpPath, content, baseUrl, port);
  stopServer();

  log('Rendered home, page, single, archive, search, author, and 404 routes.');
  dropDatabase(env);

  if (!keepFixture) {
    fs.rmSync(workDir, { force: true, recursive: true });
  }
  else {
    log(`Keeping fixture directory: ${workDir}`);
  }
}

main()
  .catch((error) => {
    console.error(`WordPress fixture smoke failed: ${error.message}`);
    dumpLogs(workDir ? path.join(workDir, 'wordpress') : null);

    if (server) {
      stopServer();
    }

    if (currentDatabaseEnv) {
      dropDatabase(currentDatabaseEnv);
    }

    if (workDir && !keepFixture) {
      fs.rmSync(workDir, { force: true, recursive: true });
    }

    process.exit(1);
  })
  .finally(() => {
    if (server) {
      stopServer();
    }
  });
