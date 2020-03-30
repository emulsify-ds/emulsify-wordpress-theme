import { configure, addDecorator, addParameters } from '@storybook/react';
import { withA11y } from '@storybook/addon-a11y';
import { action } from '@storybook/addon-actions';
import { INITIAL_VIEWPORTS } from '@storybook/addon-viewport';

// Theming
import emulsifyTheme from './emulsifyTheme';

addParameters({
  options: {
    theme: emulsifyTheme,
  },
  viewport: {
    viewports: INITIAL_VIEWPORTS,
  },
});

// GLOBAL CSS
import '../components/style.scss';

addDecorator(withA11y);

const Twig = require('twig');
const twigBEM = require('bem-twig-extension');
const twigAddAttributes = require('add-attributes-twig-extension');

Twig.cache();

twigBEM(Twig);
twigAddAttributes(Twig);

import './_attach.js';

// automatically import all files ending in *.stories.js
configure(require.context('../components', true, /\.stories\.js$/), module);

// // Gatsby's Link overrides:
// // Gatsby defines a global called ___loader to prevent its method calls from creating console errors you override it here
// global.___loader = {
//   enqueue: () => {},
//   hovering: () => {},
// };
// // Gatsby internal mocking to prevent unnecessary errors in storybook testing environment
// global.__PATH_PREFIX__ = '';
// // This is to utilized to override the window.___navigate method Gatsby defines and uses to report what path a Link would be taking us to if it wasn't inside a storybook
// window.___navigate = (pathname) => {
//   action('NavigateTo:')(pathname);
// };
