---
title: Icons
---

### How to use icons

We are using an SVG sprite generator (details [here](https://una.im/svg-icons)), which automatically takes individual SVGs from `/images/icons/src` and generates `/dist/img/sprite/sprite.svg`. Simply run `yarn build` to add your individual SVGs to this sprite (this is automatically run when running `yarn develop`).

**Usage**

The SVG component is found here: `/components/01-atoms/images/icons/_icon.twig`. See available variables in that file as well as instructions for Wordpress. Examples of usage below:

Simple: (no BEM renaming)

```
{% include "@atoms/images/icons/_icon.twig" with {
  icon_name: 'bars',
} %}
```

... creates...

```
<svg class="icon">
  <use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="/images/sprite/sprite.svg#src--bars"></use>
</svg>
```

Complex (BEM classes):

```
{% include "@atoms/images/icons/_icon.twig" with {
  icon_base_class: 'icon',
  icon_blockname: 'toggle-expand',
  icon_name: 'bars',
} %}
```

... creates...

```
<svg class="toggle-expand__icon">
  <use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="/images/sprite/sprite.svg#src--bars"></use>
</svg>
```
