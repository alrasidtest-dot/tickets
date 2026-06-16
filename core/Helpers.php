<?php
/**
 * Helpers — minimal view/i18n utilities.
 *
 * Phase 2 ships the translation function t() plus small escaping/lang
 * helpers it relies on. The translation dictionaries grow in later phases
 * (lang/ar.php, lang/en.php); the loader here does not change.
 */
class Helpers
{
    /** Supported UI languages. */
    const LANGS = ['ar', 'en'];

    /** @var array<string,array<string,string>> Loaded dictionaries, keyed by lang. */
    private static $dictionaries = [];

    /**
     * Current UI language: the session value when valid, otherwise the
     * project default. Never trusts an out-of-range value.
     *
     * @return string 'ar' | 'en'
     */
    public static function lang()
    {
        $lang = $_SESSION['lang'] ?? DEFAULT_LANG;
        return in_array($lang, self::LANGS, true) ? $lang : DEFAULT_LANG;
    }

    /**
     * Translate a key for the current language and interpolate {param}
     * placeholders from $params. Falls back to the key itself when no
     * translation exists, so missing keys are visible during development.
     *
     * @param string               $key
     * @param array<string,scalar> $params
     * @return string
     */
    public static function t($key, array $params = [])
    {
        $dict = self::dictionary(self::lang());
        $text = $dict[$key] ?? $key;

        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * Escape a value for safe output inside HTML (XSS protection).
     *
     * @param string|null $value
     * @return string
     */
    public static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format a DATETIME value (or timestamp) for display, using a single
     * locale-neutral format shared by Arabic and English (per
     * docs/BACKEND_GUIDE.md). Returns an empty string for empty/invalid input.
     *
     * @param string|int|null $value
     * @return string
     */
    public static function formatDate($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($ts === false) {
            return '';
        }
        return date('Y/m/d H:i', $ts);
    }

    /**
     * Load (and cache) the translation dictionary for a language.
     *
     * @param string $lang
     * @return array<string,string>
     */
    private static function dictionary($lang)
    {
        if (!isset(self::$dictionaries[$lang])) {
            $file = LANG_PATH . '/' . $lang . '.php';
            self::$dictionaries[$lang] = is_file($file) ? (array) require $file : [];
        }

        return self::$dictionaries[$lang];
    }
}

/**
 * Global shorthand for Helpers::t(), used throughout the views as required
 * by the i18n rule in CLAUDE.md / FRONTEND_GUIDE.md.
 *
 * @param string               $key
 * @param array<string,scalar> $params
 * @return string
 */
function t($key, array $params = [])
{
    return Helpers::t($key, $params);
}

/**
 * Global shorthand for Helpers::e() (HTML-escape for output).
 *
 * @param string|null $value
 * @return string
 */
function e($value)
{
    return Helpers::e($value);
}

/**
 * Global shorthand for Helpers::formatDate() (unified date display).
 *
 * @param string|int|null $value
 * @return string
 */
function formatDate($value)
{
    return Helpers::formatDate($value);
}
