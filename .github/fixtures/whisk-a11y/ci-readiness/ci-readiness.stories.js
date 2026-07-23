import template from './ci-readiness.twig';

export default {
  title: 'CI/Readiness fixture',
};

export const Default = {
  render: (args) => template(args),
  args: {
    title: 'CI accessibility readiness',
    summary:
      'This component gives the component-agnostic starter one real story during continuous integration.',
    url: 'https://www.emulsify.info',
    link_text: 'Visit the Emulsify documentation',
  },
};
