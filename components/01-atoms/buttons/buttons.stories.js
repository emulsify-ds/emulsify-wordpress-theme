import button from './button.twig';

import buttonData from './button.yml';
import buttonAltData from './button-alt.yml';
import buttonAlt2Data from './button-alt2.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Button' };

export const twig = () => button(buttonData);
export const twigAlt = () => button(buttonAltData);
export const twigAlt2 = () => button(buttonAlt2Data);
