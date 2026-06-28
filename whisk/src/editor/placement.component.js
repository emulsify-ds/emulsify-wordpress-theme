/* global document, setTimeout */

import {
  createNotice,
  stringList,
  stringOption,
  wpApi,
} from './utils.component.js';

function blockSignature(blocks) {
  return blocks.map((block) => `${block.clientId}:${block.name}`).join('|');
}

export function registerPlacementEnforcement(options = {}) {
  const blockNames = stringList(options.blocks);

  if (!options.enabled || blockNames.length === 0) {
    return;
  }

  const wp = wpApi();
  const addFilter = wp.hooks?.addFilter;
  const blockEditorSelect = () => wp.data?.select?.('core/block-editor');
  const blockEditorDispatch = () => wp.data?.dispatch?.('core/block-editor');

  if (!wp.data?.subscribe || !blockEditorSelect || !blockEditorDispatch) {
    return;
  }

  if (options.singleton && addFilter) {
    addFilter(
      'blocks.registerBlockType',
      'emulsify/placement/singleton-support',
      (settings, name) => {
        if (!blockNames.includes(name)) {
          return settings;
        }

        return {
          ...settings,
          supports: {
            ...(settings.supports || {}),
            multiple: false,
          },
        };
      },
    );
  }

  let isApplying = false;
  let lastSignature = '';
  let lastNoticeAt = 0;

  const noticeId = 'emulsify-placement-enforcement';
  const notice = stringOption(
    options.notice,
    'This block is limited to one instance at the top of the page.',
  );

  function showNotice() {
    const now = Date.now();

    if (now - lastNoticeAt < 2000) {
      return;
    }

    lastNoticeAt = now;
    createNotice(notice, noticeId);
  }

  function enforce(force = false) {
    if (isApplying) {
      return;
    }

    const selection = blockEditorSelect();
    const actions = blockEditorDispatch();
    const blocks = selection?.getBlocks?.() || [];

    if (!Array.isArray(blocks) || blocks.length === 0) {
      lastSignature = '';
      return;
    }

    const signature = blockSignature(blocks);

    if (!force && signature === lastSignature) {
      return;
    }

    const matches = blocks
      .map((block, index) => ({ block, index }))
      .filter((record) => blockNames.includes(record.block.name));

    if (matches.length === 0) {
      lastSignature = signature;
      return;
    }

    const keeper = matches[0];
    const duplicates = options.singleton ? matches.slice(1) : [];
    const shouldMove = !!options.requireTop && keeper.index !== 0;

    if (!shouldMove && duplicates.length === 0) {
      lastSignature = signature;
      return;
    }

    if (!actions?.removeBlock || !actions?.moveBlockToPosition) {
      return;
    }

    isApplying = true;

    try {
      duplicates.reverse().forEach(({ block }) => {
        actions.removeBlock(block.clientId, false);
      });

      if (shouldMove) {
        actions.moveBlockToPosition(
          keeper.block.clientId,
          undefined,
          undefined,
          0,
        );
      }

      showNotice();
    } finally {
      setTimeout(() => {
        const nextBlocks = blockEditorSelect()?.getBlocks?.() || [];
        lastSignature = Array.isArray(nextBlocks)
          ? blockSignature(nextBlocks)
          : '';
        isApplying = false;
      }, 0);
    }
  }

  wp.domReady?.(() => setTimeout(() => enforce(true), 0));
  wp.data.subscribe(() => enforce(false));

  document.addEventListener(
    'drop',
    () => setTimeout(() => enforce(true), 0),
    true,
  );
}
