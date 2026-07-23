import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const STARTER_SLUG = 'whisk';
const PARENT_THEME = 'emulsify';
const GENERATED_FROM = 'emulsify-wordpress';
const FALLBACK_GENERATED_FROM_VERSION = '2.0.0';
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const projectConfigPath = path.join(ROOT, 'project.emulsify.json');

const isPlainObject = (value) =>
  value !== null && typeof value === 'object' && !Array.isArray(value);

const readText = (filePath) => fs.readFileSync(filePath, 'utf8');

const writeTextIfChanged = (filePath, contents) => {
  if (readText(filePath) !== contents) {
    fs.writeFileSync(filePath, contents);
  }
};

const readJson = (filePath) => JSON.parse(readText(filePath));

const writeJsonIfChanged = (filePath, data) => {
  const contents = `${JSON.stringify(data, null, 2)}\n`;
  writeTextIfChanged(filePath, contents);
};

const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const sanitizeLabelForSource = (label) => {
  const sourceLabel = label
    .replace(/[^\p{L}\p{N} -]+/gu, '')
    .replace(/ +/g, ' ')
    .trim();

  return sourceLabel || 'New Theme';
};

const replaceThemeHeader = (contents, field, value) => {
  const pattern = new RegExp(`^(\\s*\\*\\s*${escapeRegExp(field)}:\\s*).*$`, 'mi');
  let found = false;
  const updated = contents.replace(pattern, (_match, prefix) => {
    found = true;
    return `${prefix}${value}`;
  });

  if (!found) {
    throw new Error(`Could not update "${field}" in style.css.`);
  }

  return updated;
};

const getProjectConfig = () => {
  const config = readJson(projectConfigPath);

  if (!isPlainObject(config.project)) {
    throw new Error('project.emulsify.json must contain a project object.');
  }

  const { name, machineName } = config.project;

  if (typeof name !== 'string' || name.trim() === '') {
    throw new Error('project.emulsify.json project.name must be a non-empty string.');
  }

  if (typeof machineName !== 'string' || machineName.trim() === '') {
    throw new Error('project.emulsify.json project.machineName must be a non-empty string.');
  }

  return config;
};

const updateStyleCss = ({ sourceLabel, machineName }) => {
  const filePath = path.join(ROOT, 'style.css');
  let contents = readText(filePath);

  contents = replaceThemeHeader(contents, 'Theme Name', sourceLabel);
  contents = replaceThemeHeader(contents, 'Text Domain', machineName);
  contents = replaceThemeHeader(contents, 'Template', PARENT_THEME);

  writeTextIfChanged(filePath, contents);
};

const updatePackageJson = ({ machineName }) => {
  const filePath = path.join(ROOT, 'package.json');
  const data = readJson(filePath);

  data.name = machineName;

  writeJsonIfChanged(filePath, data);
};

const getGeneratedFromVersion = () => {
  const data = readJson(path.join(ROOT, 'package.json'));

  return typeof data.version === 'string' && data.version.trim() !== ''
    ? data.version.trim()
    : FALLBACK_GENERATED_FROM_VERSION;
};

const updateLockfile = (relativePath, { machineName }) => {
  const filePath = path.join(ROOT, relativePath);

  if (!fs.existsSync(filePath)) {
    return;
  }

  const data = readJson(filePath);

  data.name = machineName;

  if (isPlainObject(data.packages) && isPlainObject(data.packages[''])) {
    data.packages[''].name = machineName;
  }

  writeJsonIfChanged(filePath, data);
};

const updateProjectConfig = (config, { name, machineName, generatedFromVersion }) => {
  config.project.platform = 'wordpress';
  config.project.name = name;
  config.project.machineName = machineName;
  config.project.generatedFrom = GENERATED_FROM;
  config.project.generatedFromVersion = generatedFromVersion;

  writeJsonIfChanged(projectConfigPath, config);
};

const updateFunctionsPhp = ({ sourceLabel }) => {
  const filePath = path.join(ROOT, 'functions.php');
  const contents = readText(filePath).replace(
    'Whisk child theme hooks.',
    `${sourceLabel} child theme hooks.`,
  );

  writeTextIfChanged(filePath, contents);
};

const updatePageTemplate = ({ machineName }) => {
  const filePath = path.join(ROOT, 'templates/page.twig');
  const contents = readText(filePath).replaceAll(
    `${STARTER_SLUG}-page`,
    `${machineName}-page`,
  );

  writeTextIfChanged(filePath, contents);
};

const walkJsonFiles = (directory) => {
  if (!fs.existsSync(directory)) {
    return [];
  }

  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const filePath = path.join(directory, entry.name);

    if (entry.isDirectory()) {
      return walkJsonFiles(filePath);
    }

    return entry.isFile() && entry.name.endsWith('.json') ? [filePath] : [];
  });
};

const updatePatternNamespaces = ({ machineName }) => {
  for (const filePath of walkJsonFiles(path.join(ROOT, 'patterns'))) {
    const data = readJson(filePath);

    if (typeof data.name === 'string' && data.name.startsWith(`${STARTER_SLUG}/`)) {
      data.name = `${machineName}/${data.name.slice(STARTER_SLUG.length + 1)}`;
      writeJsonIfChanged(filePath, data);
    }
  }
};

const main = () => {
  const config = getProjectConfig();
  const project = {
    name: config.project.name,
    sourceLabel: sanitizeLabelForSource(config.project.name),
    machineName: config.project.machineName,
    generatedFromVersion: getGeneratedFromVersion(),
  };

  updateStyleCss(project);
  updatePackageJson(project);
  updateLockfile('package-lock.json', project);
  updateLockfile('npm-shrinkwrap.json', project);
  updateProjectConfig(config, project);
  updateFunctionsPhp(project);
  updatePageTemplate(project);
  updatePatternNamespaces(project);
};

main();
