export default {
  coverageDirectory: '<rootDir>/.coverage',
  testEnvironment: 'node',
  testMatch: [
    '<rootDir>/src/**/*.test.{js,cjs,mjs}',
    '<rootDir>/components/**/*.test.{js,cjs,mjs}',
    '<rootDir>/config/**/*.test.{js,cjs,mjs}',
  ],
};
