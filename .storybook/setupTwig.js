const { resolve } = require('path');
const twigBEM = require('bem-twig-extension');
const twigAddAttributes = require('add-attributes-twig-extension');

module.exports.namespaces = {
  atoms: resolve(__dirname, '../', 'components/01-atoms'),
  molecules: resolve(__dirname, '../', 'components/02-molecules'),
  organisms: resolve(__dirname, '../', 'components/03-organisms'),
  templates: resolve(__dirname, '../', 'components/04-templates'),
};

/**
 * Configures and extends a standard twig object.
 *
 * @param {Twig} twig - twig object that should be configured and extended.
 *
 * @returns {Twig} configured twig object.
 */
module.exports.setupTwig = function setupTwig(twig) {
  twig.cache();
  twig.extendFunction("function", function() {
    return "";
  });
  twig.extendFunction("_e", function() {
    return "";
  });
  twigBEM(twig);
  twigAddAttributes(twig);
  return twig;
};
