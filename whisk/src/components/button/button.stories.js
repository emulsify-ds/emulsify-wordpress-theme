import { renderTwig } from '@emulsify/core/storybook';
import template from './button.twig';
import data from './button.data.json';
import './button.scss';

export default {
  title: 'Components/Button',
  render: renderTwig(template),
  args: data,
  argTypes: {
    text: { control: 'text' },
    url: { control: 'text' },
    modifiers: { control: 'object' },
    attributes: { control: 'object' },
  },
};

export const Default = {};

export const Secondary = {
  args: {
    ...data,
    text: 'Secondary action',
    modifiers: ['secondary'],
  },
};
