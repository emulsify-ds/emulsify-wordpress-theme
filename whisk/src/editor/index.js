/* global window */

import { defaultConfig, mergeConfig } from './default-config.component.js';
import { registerColumnsEqualHeight } from './columns-equal-height.component.js';
import { registerEmbedVariations } from './embed-variations.component.js';
import { registerFileCaption } from './file-caption.component.js';
import { registerPlacementEnforcement } from './placement.component.js';

const config = mergeConfig(
  defaultConfig,
  window.emulsifyEditorEnhancements || {},
);

registerColumnsEqualHeight(config.columnsEqualHeight);
registerFileCaption(config.fileCaption);
registerEmbedVariations(config.embedVariations);
registerPlacementEnforcement(config.placement);
