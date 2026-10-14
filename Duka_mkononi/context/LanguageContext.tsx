import AsyncStorage from "@react-native-async-storage/async-storage";
import React, { createContext, useContext, useEffect, useState } from "react";
import { I18nManager } from "react-native";

export type Lang = "sw" | "en" | "fr" | "hi" | "es" | "ur" | "de" | "zh";

// ✅ Capability #22 — Truly Multilingual Assistant.
// Urdu is the only right-to-left language in the catalogue (keep in step with
// App\Services\Ai\Locale::RTL and the website dm-lang-widget).
export const RTL_LANGUAGES: Lang[] = ["ur"];

// BCP-47 tags for Intl number/date formatting, mirroring the server-side
// App\Services\Ai\Locale::TAGS map so both surfaces format identically.
export const LOCALE_TAGS: Record<Lang, string> = {
  sw: "sw-TZ",
  en: "en-TZ",
  fr: "fr-FR",
  hi: "hi-IN",
  es: "es-ES",
  ur: "ur-PK",
  de: "de-DE",
  zh: "zh-CN",
};

// Allow the native layer to mirror when the platform restarts into RTL.
// Screens additionally mirror themselves from `isRTL` so switching language
// does not require an app reload.
I18nManager.allowRTL(true);

// Language display names in their native form
export const LANGUAGE_NAMES: Record<Lang, string> = {
  sw: "Kiswahili",
  en: "English",
  fr: "Français",
  hi: "हिन्दी",
  es: "Español",
  ur: "اردو",
  de: "Deutsch",
  zh: "中文",
};

// All available languages
export const ALL_LANGUAGES: Lang[] = ["sw", "en", "fr", "hi", "es", "ur", "de", "zh"];

// Language flag emojis for visual display
export const LANGUAGE_FLAGS: Record<Lang, string> = {
  sw: "🇹🇿",
  en: "🇬🇧",
  fr: "🇫🇷",
  hi: "🇮🇳",
  es: "🇪🇸",
  ur: "🇵🇰",
  de: "🇩🇪",
  zh: "🇨🇳",
};

// ✅ BADILISHA PATH - Jaribu relative path toka context folder
const sw = require("../locales/sw.json");
const en = require("../locales/en.json");
const fr = require("../locales/fr.json");
const hi = require("../locales/hi.json");
const es = require("../locales/es.json");
const ur = require("../locales/ur.json");
const de = require("../locales/de.json");
const zh = require("../locales/zh.json");

const translations: Record<Lang, Record<string, any>> = { sw, en, fr, hi, es, ur, de, zh };
const STORAGE_KEY = "APP_LANGUAGE";

interface LanguageContextType {
  lang: Lang;
  changeLang: (newLang: Lang) => Promise<void>;
  t: (key: string, params?: Record<string, string | number>) => string;
  availableLanguages: Lang[];
  languageNames: Record<Lang, string>;
  languageFlags: Record<Lang, string>;
  /** Capability #22: true for Urdu, so screens can mirror themselves. */
  isRTL: boolean;
  /** "rtl" | "ltr" — handy for `writingDirection` / `flexDirection`. */
  direction: "rtl" | "ltr";
  /** Locale-aware number formatting (e.g. 1,234 / 1.234 / 1,23,456). */
  numberFormat: (value: number) => string;
  /** Locale-aware date formatting via the platform Intl. */
  dateFormat: (value: Date | number) => string;
}

const LanguageContext = createContext<LanguageContextType | null>(null);

// Helper function to get nested translation values using dot notation
const getNestedTranslation = (obj: any, key: string): string => {
  const keys = key.split(".");
  let result = obj;
  for (const k of keys) {
    if (result && typeof result === "object" && k in result) {
      result = result[k];
    } else {
      return key; // Return key if not found
    }
  }
  return typeof result === "string" ? result : key;
};

export const LanguageProvider = ({ children }: { children: React.ReactNode }) => {
  const [lang, setLang] = useState<Lang>("sw");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadLang = async () => {
      try {
        const savedLang = await AsyncStorage.getItem(STORAGE_KEY);
        if (savedLang && ALL_LANGUAGES.includes(savedLang as Lang)) {
          setLang(savedLang as Lang);
        }
      } catch (error) {
        console.error("Failed to load language:", error);
      } finally {
        setLoading(false);
      }
    };
    loadLang();
  }, []);

  const changeLang = async (newLang: Lang) => {
    try {
      setLang(newLang);
      await AsyncStorage.setItem(STORAGE_KEY, newLang);
    } catch (error) {
      console.error("Failed to save language:", error);
    }
  };

  const isRTL = RTL_LANGUAGES.includes(lang);
  const direction: "rtl" | "ltr" = isRTL ? "rtl" : "ltr";

  const numberFormat = (value: number): string => {
    try {
      return new Intl.NumberFormat(LOCALE_TAGS[lang] || "sw-TZ").format(value);
    } catch {
      return String(value);
    }
  };

  const dateFormat = (value: Date | number): string => {
    try {
      return new Intl.DateTimeFormat(LOCALE_TAGS[lang] || "sw-TZ").format(value);
    } catch {
      return String(value);
    }
  };

  const t = (key: string, params?: Record<string, string | number>): string => {
    const translation = translations[lang];
    if (!translation) return key;
    let result = getNestedTranslation(translation, key);
    // Replace {param} placeholders with provided values
    if (params && result !== key) {
      Object.entries(params).forEach(([k, v]) => {
        result = result.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v));
      });
    }
    return result;
  };

  if (loading) return null;

  return (
    <LanguageContext.Provider
      value={{
        lang,
        changeLang,
        t,
        availableLanguages: ALL_LANGUAGES,
        languageNames: LANGUAGE_NAMES,
        languageFlags: LANGUAGE_FLAGS,
        isRTL,
        direction,
        numberFormat,
        dateFormat,
      }}
    >
      {children}
    </LanguageContext.Provider>
  );
};

export const useLang = () => {
  const context = useContext(LanguageContext);
  if (!context) {
    throw new Error("useLang must be used within LanguageProvider");
  }
  return context;
};