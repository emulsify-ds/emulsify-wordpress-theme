#!/usr/bin/env node

import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';

import pa11y from 'pa11y';

import a11yConfig from './a11y.config.js';
import coreA11yConfig from '../../node_modules/@emulsify/core/config/a11y.config.js';
import {
  applyProjectA11yConfig,
  logReport,
  resolvePa11yStoryIds,
  resolveStorybookBuildDir,
} from '../../node_modules/@emulsify/core/scripts/a11y.js';

applyProjectA11yConfig(a11yConfig);

const storyIds = resolvePa11yStoryIds();

if (storyIds.length === 0) {
  throw new Error(
    'No Storybook stories were discovered; accessibility checks did not run.',
  );
}

const buildDirectory = resolveStorybookBuildDir();
const contentTypes = {
  '.css': 'text/css; charset=utf-8',
  '.gif': 'image/gif',
  '.html': 'text/html; charset=utf-8',
  '.ico': 'image/x-icon',
  '.jpeg': 'image/jpeg',
  '.jpg': 'image/jpeg',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.map': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.webp': 'image/webp',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
};

const server = http.createServer((request, response) => {
  const requestUrl = new URL(request.url || '/', 'http://127.0.0.1');
  const relativePath =
    decodeURIComponent(requestUrl.pathname).replace(/^\/+/, '') || 'index.html';
  const filePath = path.resolve(buildDirectory, relativePath);
  const withinBuild =
    filePath === buildDirectory ||
    filePath.startsWith(`${buildDirectory}${path.sep}`);

  if (!withinBuild) {
    response.writeHead(403);
    response.end('Forbidden');
    return;
  }

  fs.readFile(filePath, (error, contents) => {
    if (error) {
      response.writeHead(404);
      response.end('Not found');
      return;
    }

    response.writeHead(200, {
      'Content-Type':
        contentTypes[path.extname(filePath).toLowerCase()] ||
        'application/octet-stream',
    });
    response.end(contents);
  });
});

await new Promise((resolve, reject) => {
  server.once('error', reject);
  server.listen(0, '127.0.0.1', resolve);
});

const address = server.address();
if (!address || typeof address === 'string') {
  server.close();
  throw new Error('Unable to start the local Storybook accessibility server.');
}

const pa11yOptions = {
  includeNotices: false,
  includeWarnings: false,
  runners: ['axe'],
  ...coreA11yConfig.pa11y,
  ...a11yConfig.pa11y,
};

if (process.env.CI === 'true' && process.platform === 'linux') {
  pa11yOptions.chromeLaunchConfig = {
    ...pa11yOptions.chromeLaunchConfig,
    args: [
      ...(pa11yOptions.chromeLaunchConfig?.args || []),
      '--no-sandbox',
      '--disable-setuid-sandbox',
    ],
  };
}

let hasIssues = false;

console.log(
  `Running accessibility checks against ${storyIds.length} Storybook story.`,
);

try {
  for (const storyId of storyIds) {
    const storyUrl =
      `http://127.0.0.1:${address.port}/iframe.html` +
      `?id=${encodeURIComponent(storyId)}&viewMode=story`;
    const report = await pa11y(storyUrl, pa11yOptions);
    hasIssues = logReport(report) || hasIssues;
  }
} finally {
  await new Promise((resolve) => server.close(resolve));
}

if (hasIssues) {
  process.exitCode = 1;
} else {
  console.log('Storybook accessibility checks passed.');
}
