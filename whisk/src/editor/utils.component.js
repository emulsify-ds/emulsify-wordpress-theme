/* global window */

export function wpApi() {
  return window.wp || {};
}

export function appendClass(current, className) {
  const classes = String(current || '')
    .split(/\s+/)
    .filter(Boolean);

  if (className && !classes.includes(className)) {
    classes.push(className);
  }

  return classes.join(' ');
}

export function stringList(values) {
  if (!Array.isArray(values)) {
    return [];
  }

  return values.map((value) => String(value || '').trim()).filter(Boolean);
}

export function stringOption(value, fallback) {
  const normalized = String(value || '').trim();

  return normalized || fallback;
}

export function createNotice(message, id) {
  const wp = wpApi();
  const notices = wp.data?.dispatch?.('core/notices');

  if (!notices?.createNotice || !message) {
    return;
  }

  notices.createNotice('info', message, {
    id,
    type: 'snackbar',
    isDismissible: true,
    speak: false,
  });
}
