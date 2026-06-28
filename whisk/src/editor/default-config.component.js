export const defaultConfig = {
  columnsEqualHeight: {
    enabled: false,
    attribute: 'emulsifyEqualizeHeights',
    className: 'is-equal-height',
    panelTitle: 'Columns',
    label: 'Equalize column heights',
    helpEnabled: 'Columns will match the tallest column.',
    helpDisabled: 'Columns keep their natural height.',
  },
  fileCaption: {
    enabled: false,
    attribute: 'emulsifyShowMediaCaption',
    blockClassName: 'has-media-caption',
    captionClassName: 'wp-block-file__media-caption',
    panelTitle: 'File',
    label: 'Show media caption',
    helpEnabled: 'The media library caption will be shown.',
    helpDisabled: 'The media library caption will be hidden.',
    helpUnavailable: 'Select a media file before showing its caption.',
  },
  embedVariations: {
    enabled: false,
    allowedVariationNames: [],
  },
  placement: {
    enabled: false,
    blocks: [],
    singleton: true,
    requireTop: true,
    notice: 'This block is limited to one instance at the top of the page.',
  },
};

function isPlainObject(value) {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

export function mergeConfig(base, override) {
  if (!isPlainObject(override)) {
    return base;
  }

  return Object.keys(override).reduce(
    (merged, key) => {
      const value = override[key];

      if (isPlainObject(value) && isPlainObject(merged[key])) {
        return {
          ...merged,
          [key]: mergeConfig(merged[key], value),
        };
      }

      return {
        ...merged,
        [key]: value,
      };
    },
    { ...base },
  );
}
