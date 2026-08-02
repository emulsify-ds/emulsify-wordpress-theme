export default {
  // Jest defaults rootDir to the config file's directory. Without this the
  // testMatch globs below would resolve against `config/` and silently match
  // nothing under `--passWithNoTests`.
  rootDir: '..',
  coverageDirectory: '<rootDir>/.coverage',
  testEnvironment: 'node',
  testMatch: [
    '<rootDir>/src/**/*.test.{js,cjs,mjs}',
    '<rootDir>/components/**/*.test.{js,cjs,mjs}',
    '<rootDir>/config/**/*.test.{js,cjs,mjs}',
  ],
};
