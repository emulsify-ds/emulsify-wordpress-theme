import { stringList, wpApi } from './utils.component.js';

function stripInserterScope(variation) {
  return {
    ...variation,
    scope: Array.isArray(variation.scope)
      ? variation.scope.filter((scope) => scope !== 'inserter')
      : [],
  };
}

export function registerEmbedVariations(options = {}) {
  if (!options.enabled) {
    return;
  }

  const wp = wpApi();
  const addFilter = wp.hooks?.addFilter;
  const domReady = wp.domReady;
  const allowedVariationNames = stringList(options.allowedVariationNames);

  if (!addFilter) {
    return;
  }

  const shouldHide = (variation) =>
    variation?.name && !allowedVariationNames.includes(variation.name);

  const hideVariation = (variation) => {
    if (!shouldHide(variation)) {
      return variation;
    }

    return stripInserterScope(variation);
  };

  addFilter(
    'blocks.registerBlockType',
    'emulsify/embed-variations/block-settings',
    (settings, name) => {
      if (name !== 'core/embed' || !Array.isArray(settings.variations)) {
        return settings;
      }

      return {
        ...settings,
        variations: settings.variations.map(hideVariation),
      };
    },
  );

  addFilter(
    'blocks.registerBlockVariation',
    'emulsify/embed-variations/variation-settings',
    (variationSettings, blockName) => {
      if (blockName !== 'core/embed' || !variationSettings) {
        return variationSettings;
      }

      return hideVariation(variationSettings);
    },
  );

  if (
    !domReady ||
    !wp.blocks?.getBlockVariations ||
    !wp.blocks?.unregisterBlockVariation
  ) {
    return;
  }

  domReady(() => {
    const variations = wp.blocks.getBlockVariations('core/embed') || [];

    variations.filter(shouldHide).forEach((variation) => {
      wp.blocks.unregisterBlockVariation('core/embed', variation.name);
    });
  });
}
