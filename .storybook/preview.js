import { addDecorator } from '@storybook/react';
import { useEffect } from '@storybook/client-api';
import Twig from 'twig';
import { setupTwig } from './setupTwig';

// GLOBAL CSS
import '../components/style.scss';

// Script to "attach" DOM scripts for Storybook.
import './_attach.js';

// addDecorator deprecated, but not sure how to use this otherwise.
addDecorator((storyFn) => {
  useEffect(() => Attach.attachBehaviors(), []);
  return storyFn();
});

setupTwig(Twig);
