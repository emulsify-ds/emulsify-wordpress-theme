// Simple Attach.behaviors usage for Storybook

window.Attach = { behaviors: {} };

(function(Attach) {
  Attach.throwError = function(error) {
    setTimeout(function() {
      throw error;
    }, 0);
  };

  Attach.attachBehaviors = function(context, settings) {
    context = context || document;
    settings = settings;
    const behaviors = Attach.behaviors;

    Object.keys(behaviors).forEach(function(i) {
      if (typeof behaviors[i].attach === 'function') {
        try {
          behaviors[i].attach(context, settings);
        } catch (e) {
          Attach.throwError(e);
        }
      }
    });
  };
})(Attach);
