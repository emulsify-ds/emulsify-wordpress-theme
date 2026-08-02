export default {
  // Emulsify Core resolves this from the generated theme's working directory.
  storybookBuildDir: '.out',
  // Let Storybook mount the selected story before axe inspects the iframe.
  pa11y: {
    wait: 1000,
  },
  // A11y linting is done on a component-by-component
  // basis, which results in the linter reporting some errors that
  // should be ignored. These codes and descriptions allow for those
  // errors to be targeted specifically.
  ignore: {
    // Example:
    // codes: ['WCAG2AA.Principle1.Guideline1_4.1_4_3.G18.Fail'],
    codes: [],
    // Example:
    // descriptions: ['This color pair is supplied by third-party content.'],
    descriptions: [],
  },
  // List of storybook component IDs defined and used in this project.
  // Example:
  // components: ['components-example--default'],
  components: [],
};
