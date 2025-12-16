import { createI18n } from 'vue-i18n';
import ar from './ar.json';
import en from './en.json';

type Locale = 'ar' | 'en';

const languages: Record<Locale, { dir: 'rtl' | 'ltr' }> = {
    ar: { dir: 'rtl' },
    en: { dir: 'ltr' },
};

const getDefaultLocale = (): Locale => {
    if (typeof window === 'undefined') {
        return 'ar';
    }
    // Server-set locale from URL takes priority (via HTML lang attribute)
    const htmlLang = document.documentElement.lang as Locale;
    if (htmlLang === 'ar' || htmlLang === 'en') {
        return htmlLang;
    }
    // Fallback to localStorage
    const stored = localStorage.getItem('locale') as Locale | null;
    if (stored && (stored === 'ar' || stored === 'en')) {
        return stored;
    }
    return 'ar';
};

const defaultLocale = getDefaultLocale();

const i18n = createI18n({
    legacy: false, // use Composition API
    locale: defaultLocale,
    fallbackLocale: 'en',
    messages: {
        en,
        ar,
    },
});

export function initializeLocale() {
    if (typeof window === 'undefined') {
        return;
    }

    const locale = defaultLocale;
    const lang = languages[locale];

    document.documentElement.lang = locale;
    document.documentElement.dir = lang.dir;
}

export default i18n;
