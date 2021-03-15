import button from './button.twig';

import buttonData from './button.yml';
import buttonAltData from './button-alt.yml';
import buttonAlt2Data from './button-alt2.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Button' };

export const example = () => button(buttonData);
export const alt = () => button(buttonAltData);
export const alt2 = () => button(buttonAlt2Data);
