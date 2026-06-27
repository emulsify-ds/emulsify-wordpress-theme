#!/usr/bin/env node

const childProcess = require('child_process');
const path = require('path');

const repoRoot = path.resolve(__dirname, '../..');

function run(label, command, args) {
  console.log(`\n[pr-validation] ${label}`);
  const result = childProcess.spawnSync(command, args, {
    cwd: repoRoot,
    env: process.env,
    stdio: 'inherit',
  });

  if (result.status !== 0) {
    process.exit(result.status || 1);
  }
}

run('Validate Composer metadata', 'composer', ['validate', '--no-check-publish', '--strict']);
run('Install Composer dependencies for Twig smoke coverage', 'composer', [
  'install',
  '--no-dev',
  '--no-interaction',
  '--no-progress',
  '--prefer-dist',
]);
run('Run PHP lint', 'npm', ['run', 'lint:php']);
run('Run Twig attribute helper smoke test', 'npm', ['run', 'smoke:attributes']);
run('Run child theme generator smoke test', 'npm', ['run', 'smoke:child-theme-generator']);
run('Run component locator smoke test', 'npm', ['run', 'smoke:component-locator']);
run('Run parent theme filter smoke test', 'npm', ['run', 'smoke:theme-filters']);
run('Install Whisk dependencies', 'npm', ['run', 'whisk:install']);
run('Build Whisk Core 4/Vite assets', 'npm', ['run', 'whisk:build']);
