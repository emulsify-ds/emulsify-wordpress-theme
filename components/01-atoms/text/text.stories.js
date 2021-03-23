import headings from './headings/headings.twig';
import blockquote from './text/02-blockquote.twig';
import pre from './text/05-pre.twig';
import paragraph from './text/03-inline-elements.twig';

import blockquoteData from './text/blockquote.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Text' };

export const headingsExamples = () => headings();

export const blockquoteExample = () => blockquote(blockquoteData);

export const preformatted = () => pre();

export const random = () => paragraph();
