/**
 * Duka Mkononi - Complete Translation Engine v4.1
 * Fixes: word-boundary checks, PASS 3 on original text, short-value filters
 * Handles ALL text via: data-i18n → exact textContent → substring/word matching
 */

(function () {
  "use strict";

  var STORAGE_KEY = "duka_mkononi_lang";
  var DEFAULT_LANG = "sw";
  var ALL_LANGUAGES = ["sw","en","fr","hi","es","ur","de","zh"];
  var LANGUAGE_NAMES = { sw:"Kiswahili", en:"English", fr:"Fran\u00e7ais", hi:"\u0939\u093f\u0928\u094d\u0926\u0940", es:"Espa\u00f1ol", ur:"\u0627\u0631\u062f\u0648", de:"Deutsch", zh:"\u4e2d\u6587" };
  var LANGUAGE_FLAGS = { sw:"\ud83c\uddf9\ud83c\uddff", en:"\ud83c\uddec\ud83c\udde7", fr:"\ud83c\uddeb\ud83c\uddf7", hi:"\ud83c\uddee\ud83c\uddf3", es:"\ud83c\uddea\ud83c\uddf8", ur:"\ud83c\uddf5\ud83c\uddf0", de:"\ud83c\udde9\ud83c\uddea", zh:"\ud83c\udde8\ud83c\uddf3" };

  var currentLang = localStorage.getItem(STORAGE_KEY) || DEFAULT_LANG;
  var translations = {};
  var swValues = [];
  var swMap = {};
  var isInit = false;

  function getNested(obj, key) {
    var parts = key.split(".");
    var r = obj;
    for (var i = 0; i < parts.length; i++) {
      if (!r || typeof r !== "object") return null;
      r = r[parts[i]];
    }
    return typeof r === "string" ? r : null;
  }

  function t(key, params) {
    if (!translations[currentLang]) return key;
    var result = getNested(translations[currentLang], key);
    if (!result) return key.split(".").pop().replace(/_/g, " ");
    if (params) {
      for (var k in params) {
        if (params.hasOwnProperty(k))
          result = result.split("{" + k + "}").join(String(params[k]));
      }
    }
    return result;
  }

  function normalize(str) {
    if (!str) return "";
    return str.replace(/\s+/g, " ").trim();
  }

  /** Check if character at position is a word boundary */
  function isWordBoundary(text, pos) {
    if (pos <= 0 || pos >= text.length) return true;
    var before = text[pos - 1];
    var after = text[pos];
    // Word boundary if either side is non-alphabetic
    var isBeforeLetter = (before >= "a" && before <= "z") || (before >= "A" && before <= "Z");
    var isAfterLetter = (after >= "a" && after <= "z") || (after >= "A" && after <= "Z");
    return !isBeforeLetter || !isAfterLetter;
  }

  function buildIndex(obj) {
    var vals = [];
    var map = {};
    function walk(o, prefix) {
      for (var k in o) {
        if (!o.hasOwnProperty(k)) continue;
        var key = prefix ? prefix + "." + k : k;
        if (typeof o[k] === "object" && o[k] !== null) walk(o[k], key);
        else if (typeof o[k] === "string") {
          var text = normalize(o[k]);
          if (text.length > 0) {
            vals.push({ key: key, text: text });
            var lower = text.toLowerCase();
            if (!map[lower]) map[lower] = key;
          }
        }
      }
    }
    walk(obj, "");
    vals.sort(function (a, b) { return b.text.length - a.text.length; });
    return { values: vals, map: map };
  }

  function findKey(text) {
    text = normalize(text);
    if (text.length < 2) return null;
    var lower = text.toLowerCase();
    if (swMap[lower]) return swMap[lower];
    for (var i = 0; i < swValues.length; i++) {
      if (swValues[i].text.toLowerCase() === lower) return swValues[i].key;
    }
    return null;
  }

  /**
   * Find known Swahili substrings within text, with word-boundary checks.
   * Only matches values >= 5 chars in substring mode to avoid false positives.
   */
  function findSubstringMatches(text) {
    var normalized = normalize(text);
    if (normalized.length < 3) return null;
    var lower = normalized.toLowerCase();
    var matches = [];

    for (var i = 0; i < swValues.length; i++) {
      var swText = swValues[i].text;
      // In substring mode, require >= 5 chars to avoid common short-word false positives
      if (swText.length < 5) continue;
      var swLower = swText.toLowerCase();

      var idx = 0;
      while ((idx = lower.indexOf(swLower, idx)) !== -1) {
        // Verify word boundaries
        var beforeOk = idx === 0 || isWordBoundary(lower, idx);
        var afterOk = (idx + swLower.length >= lower.length) || isWordBoundary(lower, idx + swLower.length);
        if (beforeOk && afterOk) {
          var translated = t(swValues[i].key);
          if (translated && translated !== swValues[i].key && translated !== swText) {
            matches.push({
              original: normalized.substring(idx, idx + swText.length),
              translation: translated,
              start: idx,
              end: idx + swText.length
            });
          }
          idx += swLower.length;
        } else {
          idx++;
        }
      }
    }

    // Sort by position
    matches.sort(function (a, b) { return a.start - b.start; });

    // Merge overlapping
    if (matches.length > 1) {
      var merged = [matches[0]];
      for (var m = 1; m < matches.length; m++) {
        var last = merged[merged.length - 1];
        if (matches[m].start <= last.end) {
          if ((matches[m].end - matches[m].start) > (last.end - last.start))
            merged[merged.length - 1] = matches[m];
        } else {
          merged.push(matches[m]);
        }
      }
      matches = merged;
    }

    return matches.length > 0 ? matches : null;
  }

  function loadLang(lang, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "/locales/" + lang + ".json", true);
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          translations[lang] = JSON.parse(xhr.responseText);
          if (lang === "sw") {
            var idx = buildIndex(translations["sw"]);
            swValues = idx.values;
            swMap = idx.map;
          }
          if (cb) cb(true);
        } catch (e) { if (cb) cb(false); }
      } else { if (cb) cb(false); }
    };
    xhr.onerror = function () { if (cb) cb(false); };
    xhr.send();
  }

  /** Get the direct text of an element (only text nodes, not descendants) */
  function getDirectText(el) {
    var text = "";
    for (var i = 0; i < el.childNodes.length; i++) {
      if (el.childNodes[i].nodeType === 3) text += el.childNodes[i].textContent;
    }
    return normalize(text);
  }

  function setText(el, translation) {
    if (!el || !el.tagName) return;
    var tag = el.tagName.toLowerCase();
    if (tag === "input" || tag === "textarea") {
      if (el.getAttribute("data-i18n-type") === "placeholder") el.placeholder = translation;
      else el.value = translation;
      return;
    }
    if (tag === "img") { el.alt = translation; return; }
    if (tag === "title") { document.title = translation; return; }

    var hasChildEls = false;
    for (var i = 0; i < el.childNodes.length; i++) {
      if (el.childNodes[i].nodeType === 1) { hasChildEls = true; break; }
    }

    if (hasChildEls) {
      // Replace text nodes while preserving child elements
      for (var j = 0; j < el.childNodes.length; j++) {
        var node = el.childNodes[j];
        if (node.nodeType === 3) {
          var txt = normalize(node.textContent);
          if (txt.length > 1) {
            var k = findKey(txt);
            if (k) {
              var trans = t(k);
              if (trans && trans !== k) node.textContent = node.textContent.replace(txt, trans);
            }
          }
        }
      }
    } else {
      el.textContent = translation;
    }
  }

  /** Mark element as translated so PASS 2 & 3 can skip it */
  function markTranslated(el) {
    if (el) el.setAttribute("data-i18n-done", "1");
  }

  function translatePage() {
    if (!translations["sw"] || !translations[currentLang]) return;
    if (currentLang === "sw") { updateUI(); return; }

    // ---- PASS 1: data-i18n attributes (explicit annotations) ----
    document.querySelectorAll("[data-i18n]").forEach(function (el) {
      var key = el.getAttribute("data-i18n");
      if (!key) return;
      var trans = t(key);
      if (trans && trans !== key) { setText(el, trans); markTranslated(el); }
    });

    // ---- PASS 2: Exact textContent matching ----
    var allEls = document.body.querySelectorAll("*");
    var matched = {};

    // Reverse order: inner elements first
    for (var e = allEls.length - 1; e >= 0; e--) {
      var el = allEls[e];
      var tag = el.tagName ? el.tagName.toLowerCase() : "";
      if (["script","style","noscript"].indexOf(tag) !== -1) continue;
      if (el.closest && el.closest(".duka-lang-switcher")) continue;
      if (el.hasAttribute("data-i18n")) continue;
      if (el.hasAttribute("data-i18n-done")) continue;

      // --- Placeholder ---
      if (el.hasAttribute("placeholder")) {
        var ph = normalize(el.getAttribute("placeholder"));
        if (ph.length > 1 && !matched["ph:" + ph]) {
          var phKey = ph.length >= 3 ? findKey(ph) : null;
          if (phKey) {
            var phTrans = t(phKey);
            if (phTrans && phTrans !== phKey) {
              el.placeholder = phTrans;
              matched["ph:" + ph] = true;
            }
          }
        }
      }

      // --- Value attribute ---
      if (el.hasAttribute("value") && (tag === "input" || tag === "button")) {
        var val = normalize(el.getAttribute("value"));
        if (val.length > 1 && !matched["val:" + val]) {
          var valKey = val.length >= 3 ? findKey(val) : null;
          if (valKey) {
            var valTrans = t(valKey);
            if (valTrans && valTrans !== valKey && valTrans !== val) {
              el.setAttribute("value", valTrans);
              matched["val:" + val] = true;
            }
          }
        }
      }

      // --- Direct text matching ---
      var directText = getDirectText(el);
      if (directText.length < 3) continue; // Skip very short text

      // Check if this element's full textContent matches a key
      var fullText = normalize(el.textContent);
      var key = fullText.length >= 3 ? findKey(fullText) : null;

      if (key && !matched[key]) {
        // Only translate if no child element already has a match
        var hasChildMatch = false;
        var kids = el.querySelectorAll("*");
        for (var k = 0; k < kids.length; k++) {
          if (kids[k] !== el && kids[k].hasAttribute("data-i18n-done")) {
            hasChildMatch = true; break;
          }
        }

        if (!hasChildMatch) {
          var trans = t(key);
          if (trans && trans !== key) {
            setText(el, trans);
            matched[key] = true;
            markTranslated(el);
          }
        }
      }
    }

    // ---- PASS 3: Substring matching on REMAINING untranslated text ----
    var walker = document.createTreeWalker(
      document.body, NodeFilter.SHOW_TEXT, {
        acceptNode: function (node) {
          if (!node.parentElement) return NodeFilter.FILTER_REJECT;
          // Skip if parent has been translated (data-i18n or done marker)
          if (node.parentElement.hasAttribute("data-i18n")) return NodeFilter.FILTER_REJECT;
          if (node.parentElement.hasAttribute("data-i18n-done")) return NodeFilter.FILTER_REJECT;
          if (node.parentElement.closest(".duka-lang-switcher")) return NodeFilter.FILTER_REJECT;
          if (["script","style","noscript"].indexOf(node.parentElement.tagName.toLowerCase()) !== -1)
            return NodeFilter.FILTER_REJECT;
          return NodeFilter.FILTER_ACCEPT;
        }
      }
    );

    var textNodes = [];
    var node;
    while ((node = walker.nextNode())) { textNodes.push(node); }

    for (var tn = 0; tn < textNodes.length; tn++) {
      var txt = normalize(textNodes[tn].textContent);
      if (txt.length < 3) continue;

      // Try exact match first
      var exactKey = findKey(txt);
      if (exactKey) {
        var trans = t(exactKey);
        if (trans && trans !== exactKey && trans !== txt) {
          textNodes[tn].textContent = trans;
          continue;
        }
      }

      // Substring match with word boundaries
      var subMatches = findSubstringMatches(txt);
      if (subMatches) {
        var newText = textNodes[tn].textContent;
        for (var s = subMatches.length - 1; s >= 0; s--) {
          var sm = subMatches[s];
          // Find the match position in the original (non-normalized) text
          var origLower = sm.original.toLowerCase();
          var textLower = textNodes[tn].textContent.toLowerCase();
          var idx = textLower.indexOf(origLower);
          if (idx !== -1) {
            newText = newText.substring(0, idx) + sm.translation + newText.substring(idx + sm.original.length);
          }
        }
        if (newText !== textNodes[tn].textContent) {
          textNodes[tn].textContent = newText;
        }
      }
    }

    // ---- PASS 4: title ----
    var titleEl = document.querySelector("title");
    if (titleEl) {
      var titleKey = titleEl.getAttribute("data-i18n") || findKey(normalize(titleEl.textContent));
      if (titleKey) {
        var titleTrans = t(titleKey);
        if (titleTrans && titleTrans !== titleKey) document.title = titleTrans;
      }
    }

    // Cleanup markers
    document.querySelectorAll("[data-i18n-done]").forEach(function (el) {
      el.removeAttribute("data-i18n-done");
    });

    updateUI();
    document.documentElement.lang = currentLang;
  }

  function updateUI() {
    var curr = document.querySelector(".duka-lang-current-text");
    if (curr) curr.textContent = LANGUAGE_FLAGS[currentLang] + " " + LANGUAGE_NAMES[currentLang];
    var dd = document.getElementById("duka-lang-dropdown");
    if (!dd) return;
    dd.querySelectorAll(".duka-lang-option").forEach(function (opt) {
      var isActive = opt.getAttribute("data-lang") === currentLang;
      opt.classList.toggle("active", isActive);
      var check = opt.querySelector(".check");
      if (isActive && !check) opt.insertAdjacentHTML("beforeend", '<span class="check">&#10003;</span>');
      else if (!isActive && check) check.remove();
    });
  }

  function createSwitcher() {
    if (document.querySelector(".duka-lang-switcher")) return;

    var s = document.createElement("style");
    s.textContent =
      ".duka-lang-switcher{position:fixed;top:12px;right:12px;z-index:99999;font-family:'Inter',-apple-system,sans-serif;font-size:14px}" +
      ".duka-lang-btn{display:flex;align-items:center;gap:6px;padding:8px 14px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;cursor:pointer;font-weight:600;color:#2c3e50;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all .2s}" +
      ".duka-lang-btn:hover{box-shadow:0 4px 12px rgba(0,0,0,0.12);transform:translateY(-1px)}" +
      ".duka-lang-btn:active{transform:scale(0.97)}" +
      ".duka-lang-dropdown{display:none;position:absolute;top:100%;right:0;margin-top:4px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.12);overflow:hidden;min-width:180px;max-height:320px;overflow-y:auto}" +
      ".duka-lang-dropdown.show{display:block}" +
      ".duka-lang-option{display:flex;align-items:center;gap:8px;padding:10px 14px;cursor:pointer;transition:background .15s;color:#2c3e50;border:none;background:none;width:100%;text-align:left;font-size:14px;font-family:'Inter',sans-serif}" +
      ".duka-lang-option:hover{background:#f5f5f5}" +
      ".duka-lang-option.active{background:#e8f4fd;color:#2563eb;font-weight:600}" +
      ".duka-lang-option .flag{font-size:16px}.duka-lang-option .name{flex:1}" +
      ".duka-lang-option .check{color:#2563eb;font-weight:700;font-size:12px}" +
      ".duka-lang-arrow{font-size:10px;color:#888;transition:transform .2s}" +
      ".duka-lang-btn.open .duka-lang-arrow{transform:rotate(180deg)}";
    document.head.appendChild(s);

    var w = document.createElement("div");
    w.className = "duka-lang-switcher";
    var opts = "";
    for (var i = 0; i < ALL_LANGUAGES.length; i++) {
      var c = ALL_LANGUAGES[i];
      opts += '<button class="duka-lang-option' + (c === currentLang ? ' active' : '') +
        '" data-lang="' + c + '">' +
        '<span class="flag">' + LANGUAGE_FLAGS[c] + '</span>' +
        '<span class="name">' + LANGUAGE_NAMES[c] + '</span>' +
        (c === currentLang ? '<span class="check">&#10003;</span>' : '') + '</button>';
    }
    w.innerHTML = '<div class="duka-lang-btn" id="duka-lang-btn">' +
      '<span class="duka-lang-current-text">' + LANGUAGE_FLAGS[currentLang] + " " + LANGUAGE_NAMES[currentLang] + '</span>' +
      '<span class="duka-lang-arrow">&#9660;</span></div>' +
      '<div class="duka-lang-dropdown" id="duka-lang-dropdown">' + opts + '</div>';
    document.body.appendChild(w);

    document.getElementById("duka-lang-btn").addEventListener("click", function () {
      document.getElementById("duka-lang-dropdown").classList.toggle("show");
      this.classList.toggle("open");
    });
    w.querySelectorAll(".duka-lang-option").forEach(function (opt) {
      opt.addEventListener("click", function () { changeLang(this.getAttribute("data-lang")); });
    });
    document.addEventListener("click", function (e) {
      if (!e.target.closest(".duka-lang-switcher")) {
        var dd = document.getElementById("duka-lang-dropdown");
        if (dd && dd.classList.contains("show")) { dd.classList.remove("show"); var btn = document.getElementById("duka-lang-btn"); if (btn) btn.classList.remove("open"); }
      }
    });
  }

  function changeLang(newLang) {
    if (newLang === currentLang) { var dd = document.getElementById("duka-lang-dropdown"); if (dd) dd.classList.remove("show"); return; }
    function apply() {
      currentLang = newLang;
      localStorage.setItem(STORAGE_KEY, newLang);
      // Reload the page to get fresh original text
      location.reload();
    }
    if (!translations[newLang]) {
      loadLang(newLang, function (ok) {
        if (!ok && newLang !== "en") loadLang("en", function () { apply(); });
        else apply();
      });
    } else { apply(); }
  }

  function init() {
    if (isInit) return;
    loadLang("sw", function () {
      if (currentLang !== "sw") loadLang(currentLang, function () { createSwitcher(); translatePage(); isInit = true; });
      else { createSwitcher(); isInit = true; }
    });
  }

  window.DukaLang = { t: t, changeLang: changeLang, translatePage: translatePage, getCurrentLang: function () { return currentLang; } };

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
