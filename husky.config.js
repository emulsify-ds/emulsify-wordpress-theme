module.exports = {
  hooks: {
    // Retained for projects still using Husky's JS config loader; package.json
    // exposes the same hook command for current installs.
    'commit-msg': 'commitlint -E HUSKY_GIT_PARAMS',
  },
};
