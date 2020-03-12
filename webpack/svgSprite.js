function requireAll(r) {
  r.keys().forEach(r);
}

requireAll(require.context('../western-up-twig/images/', true, /\.svg$/));
