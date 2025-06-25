import {Locale} from "date-fns";
import {cs} from "date-fns/locale/cs";

export default async function findDateFnsLocale() : Promise<Locale> {
    try {
        const dateFnsLocaleModule = await import(`/dist/locales/date-fns/${document.documentElement.lang}.js`);
        return dateFnsLocaleModule.default as Locale;
    } catch (e) {}

    // Try country variant
    if (document.documentElement.dataset.langCountry) {
        try {
            const dateFnsLocaleModule = await import(`/dist/locales/date-fns/${document.documentElement.lang}-{document.documentElement.dataset.langCountry}.js`);
            return dateFnsLocaleModule.default as Locale;
        } catch (e) {}
    }

    // Fallback to known locale (czech)
    return cs;
}