import { appendClass, stringOption, wpApi } from './utils.component.js';

export function registerColumnsEqualHeight(options = {}) {
  if (!options.enabled) {
    return;
  }

  const wp = wpApi();
  const addFilter = wp.hooks?.addFilter;
  const createElement = wp.element?.createElement;
  const Fragment = wp.element?.Fragment;
  const createHigherOrderComponent = wp.compose?.createHigherOrderComponent;
  const InspectorControls = (wp.blockEditor || wp.editor)?.InspectorControls;
  const PanelBody = wp.components?.PanelBody;
  const ToggleControl = wp.components?.ToggleControl;

  if (
    !addFilter ||
    !createElement ||
    !Fragment ||
    !createHigherOrderComponent ||
    !InspectorControls ||
    !PanelBody ||
    !ToggleControl
  ) {
    return;
  }

  const attribute = stringOption(options.attribute, 'emulsifyEqualizeHeights');
  const className = stringOption(options.className, 'is-equal-height');

  addFilter(
    'blocks.registerBlockType',
    'emulsify/columns-equal-height/attribute',
    (settings, name) => {
      if (name !== 'core/columns') {
        return settings;
      }

      return {
        ...settings,
        attributes: {
          ...(settings.attributes || {}),
          [attribute]: {
            type: 'boolean',
            default: false,
          },
        },
      };
    },
  );

  const withEqualHeightControl = createHigherOrderComponent((BlockEdit) => {
    return function EqualHeightControl(props) {
      if (props.name !== 'core/columns') {
        return createElement(BlockEdit, props);
      }

      const enabled = !!props.attributes?.[attribute];

      return createElement(
        Fragment,
        null,
        createElement(BlockEdit, props),
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            {
              title: stringOption(options.panelTitle, 'Columns'),
              initialOpen: true,
            },
            createElement(ToggleControl, {
              label: stringOption(options.label, 'Equalize column heights'),
              help: enabled
                ? stringOption(
                    options.helpEnabled,
                    'Columns will match the tallest column.',
                  )
                : stringOption(
                    options.helpDisabled,
                    'Columns keep their natural height.',
                  ),
              checked: enabled,
              onChange: (value) =>
                props.setAttributes({ [attribute]: !!value }),
            }),
          ),
        ),
      );
    };
  }, 'withEmulsifyColumnsEqualHeightControl');

  addFilter(
    'editor.BlockEdit',
    'emulsify/columns-equal-height/control',
    withEqualHeightControl,
  );

  addFilter(
    'blocks.getSaveContent.extraProps',
    'emulsify/columns-equal-height/save-class',
    (extraProps, blockType, attributes) => {
      if (blockType.name !== 'core/columns' || !attributes?.[attribute]) {
        return extraProps;
      }

      return {
        ...extraProps,
        className: appendClass(extraProps.className, className),
      };
    },
  );

  const withEditorClass = createHigherOrderComponent((BlockListBlock) => {
    return function EqualHeightEditorClass(props) {
      if (
        props.block?.name !== 'core/columns' ||
        !props.block?.attributes?.[attribute]
      ) {
        return createElement(BlockListBlock, props);
      }

      return createElement(BlockListBlock, {
        ...props,
        className: appendClass(props.className, className),
      });
    };
  }, 'withEmulsifyColumnsEqualHeightEditorClass');

  addFilter(
    'editor.BlockListBlock',
    'emulsify/columns-equal-height/editor-class',
    withEditorClass,
  );
}
