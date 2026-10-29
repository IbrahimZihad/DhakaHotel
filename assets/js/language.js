/* global window, document */
(function () {
  'use strict';

  var DH = window.DH || {};
  window.DH = DH;

  var payloadEl = document.getElementById('i18n-payload');
  var htmlLang = document.documentElement.getAttribute('lang') || 'bn';
  var payload = { lang: htmlLang, dict: { bn: {}, en: {} }, languages: ['bn', 'en'] };
  if (payloadEl) {
    try {
      payload = JSON.parse(payloadEl.textContent);
    } catch (e) { /* keep defaults */ }
  }

  var bnMap = { '0': '০', '1': '১', '2': '২', '3': '৩', '4': '৪', '5': '৫', '6': '৬', '7': '৭', '8': '৮', '9': '৯' };

  function bnDigits(value) {
    return String(value).replace(/[0-9]/g, function (d) { return bnMap[d]; });
  }

  var i18n = {
    lang: payload.lang || 'bn',
    languages: payload.languages || ['bn', 'en'],
    dict: payload.dict || { bn: {}, en: {} },

    t: function (key, params) {
      var dict = this.dict[this.lang] || {};
      var value = dict[key];
      if (value === undefined || value === null) {
        value = (this.dict.en || {})[key] || key;
      }
      if (params) {
        Object.keys(params).forEach(function (name) {
          value = value.split(':' + name).join(String(params[name]));
          value = value.split('{' + name + '}').join(String(params[name]));
        });
      }
      return value;
    },

    formatCount: function (count) {
      return this.lang === 'bn' ? bnDigits(count) : String(count);
    },

    money: function (cents) {
      var negative = cents < 0;
      var abs = Math.abs(Math.round(cents));
      var whole = Math.floor(abs / 100);
      var frac = abs % 100;
      var text = whole.toLocaleString('en-US');
      if (frac > 0) {
        text += '.' + (frac < 10 ? '0' : '') + frac;
      } else if (frac === 0) {
        text = whole.toLocaleString('en-US');
      }
      if (this.lang === 'bn') {
        text = bnDigits(text);
      }
      return (negative ? '−' : '') + '৳' + text;
    },

    apply: function (root) {
      root = root || document;
      root.querySelectorAll('[data-i18n]').forEach(function (el) {
        el.textContent = i18n.t(el.getAttribute('data-i18n'));
      });
      root.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
        el.setAttribute('placeholder', i18n.t(el.getAttribute('data-i18n-placeholder')));
      });
      root.querySelectorAll('[data-i18n-title]').forEach(function (el) {
        el.setAttribute('title', i18n.t(el.getAttribute('data-i18n-title')));
      });
      root.querySelectorAll('[data-i18n-label]').forEach(function (el) {
        el.setAttribute('aria-label', i18n.t(el.getAttribute('data-i18n-label')));
      });
    },

    /**
     * Persist a language for the current context and update in place.
     * Public pages should normally use the plain `?lang=` links instead.
     */
    switchTo: function (lang, endpoint, options) {
      options = options || {};
      if (this.languages.indexOf(lang) === -1) {
        return Promise.reject(new Error('unsupported language'));
      }
      if (!endpoint) {
        endpoint = (DH.apiUrl ? DH.apiUrl().replace(/api\/cart\.php$/, '') : '') + 'api/set-language.php';
      }
      return DH.post(endpoint, { lang: lang }).then(function (data) {
        if (!data || !data.ok) {
          throw new Error((data && data.message) || 'language update failed');
        }
        i18n.lang = lang;
        if (data.dict) {
          i18n.dict[lang] = data.dict;
        }
        document.documentElement.setAttribute('lang', lang);
        i18n.apply(document);
        document.dispatchEvent(new CustomEvent('dh:languagechange', { detail: { lang: lang } }));
        if (options.reload) {
          window.location.reload();
        }
        return lang;
      });
    }
  };

  DH.i18n = i18n;
  DH.bnDigits = bnDigits;
  DH.formatCount = function (count) { return i18n.formatCount(count); };

  var hasDict = Object.keys((i18n.dict[i18n.lang]) || {}).length > 0;
  if (hasDict) {
    i18n.apply(document);
  }
})();
