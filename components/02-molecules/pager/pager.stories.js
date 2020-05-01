import React from 'react';

import pager from './pager.twig';

import pagerData from './pager.yml';
import pagerBothData from './pager-both.yml';
import pagerPrevData from './pager-prev.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Menus/Pager' };

export const pagerExample = () => (
  <>
    <h3>Pager:</h3>
    <div dangerouslySetInnerHTML={{ __html: pager(pagerData) }} />
    <h3>Pager with next and previous links:</h3>
    <div
      dangerouslySetInnerHTML={{
        __html: pager({ ...pagerData, ...pagerBothData }),
      }}
    />
    <h3>Pager with previous link:</h3>
    <div
      dangerouslySetInnerHTML={{
        __html: pager({ ...pagerData, ...pagerPrevData }),
      }}
    />
  </>
);
