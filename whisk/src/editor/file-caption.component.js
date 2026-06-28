import { appendClass, stringOption, wpApi } from './utils.component.js';

export function registerFileCaption(options = {}) {
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

  const attribute = stringOption(options.attribute, 'emulsifyShowMediaCaption');
  const blockClassName = stringOption(
    options.blockClassName,
    'has-media-caption',
  );

  addFilter(
    'blocks.registerBlockType',
    'emulsify/file-caption/attribute',
    (settings, name) => {
      if (name !== 'core/file') {
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

  const withCaptionControl = createHigherOrderComponent((BlockEdit) => {
    return function FileCaptionControl(props) {
      if (props.name !== 'core/file') {
        return createElement(BlockEdit, props);
      }

      const enabled = !!props.attributes?.[attribute];
      const attachmentId =
        props.attributes?.id || props.attributes?.fileId || 0;
      const disabled = !attachmentId;

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
              title: stringOption(options.panelTitle, 'File'),
              initialOpen: true,
            },
            createElement(ToggleControl, {
              label: stringOption(options.label, 'Show media caption'),
              help: disabled
                ? stringOption(
                    options.helpUnavailable,
                    'Select a media file before showing its caption.',
                  )
                : enabled
                  ? stringOption(
                      options.helpEnabled,
                      'The media library caption will be shown.',
                    )
                  : stringOption(
                      options.helpDisabled,
                      'The media library caption will be hidden.',
                    ),
              checked: enabled,
              disabled,
              onChange: (value) =>
                props.setAttributes({ [attribute]: !!value }),
            }),
          ),
        ),
      );
    };
  }, 'withEmulsifyFileCaptionControl');

  addFilter(
    'editor.BlockEdit',
    'emulsify/file-caption/control',
    withCaptionControl,
  );

  const withEditorClass = createHigherOrderComponent((BlockListBlock) => {
    return function FileCaptionEditorClass(props) {
      if (
        props.block?.name !== 'core/file' ||
        !props.block?.attributes?.[attribute]
      ) {
        return createElement(BlockListBlock, props);
      }

      return createElement(BlockListBlock, {
        ...props,
        className: appendClass(props.className, blockClassName),
      });
    };
  }, 'withEmulsifyFileCaptionEditorClass');

  addFilter(
    'editor.BlockListBlock',
    'emulsify/file-caption/editor-class',
    withEditorClass,
  );
}
