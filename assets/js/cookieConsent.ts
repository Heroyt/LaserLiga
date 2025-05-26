// Cookie consent logic for Matomo analytics
const consentKey = 'cookieConsentAnalytics';

declare global {
    interface Window {
        _paq: any[];
    }
}

type ConsentValues = {
    necessary: boolean;
    preferences: boolean;
    statistics: boolean;
    marketing: boolean;
};

function setMatomoConsent(granted: boolean) {
    if (typeof window._paq !== 'undefined' && Array.isArray(window._paq)) {
        if (granted) {
            window._paq.push(['setConsentGiven']);
            window._paq.push(['setCookieConsentGiven']);
            _mtm.push({event: 'consent-given'});
        } else {
            _paq.push(['forgetConsentGiven']);
            _paq.push(['forgetCookieConsentGiven']);
        }
    }
}

function isConsentSet(): boolean {
    const consent = localStorage.getItem(consentKey);
    return !!consent;
}

export function getRememberedConsent(): ConsentValues {
    const consent = localStorage.getItem(consentKey);
    if (consent) {
        try {
            const parsed = JSON.parse(consent);
            // Validate structure
            if (typeof parsed === 'object' && parsed !== null &&
                'necessary' in parsed && 'preferences' in parsed &&
                'statistics' in parsed && 'marketing' in parsed) {
                return {
                    necessary: true, // Necessary cookies are always true
                    preferences: parsed.preferences ?? true,
                    statistics: parsed.statistics ?? true,
                    marketing: parsed.marketing ?? false,
                };
            }
        } catch (e) {
            // Ignore parsing errors and return default values
        }
    }
    return {
        necessary: true,
        preferences: false,
        statistics: false,
        marketing: false,
    };
}

function saveConsent(consent: ConsentValues) : void {
    try {
        localStorage.setItem(consentKey, JSON.stringify(consent));
    } catch (e) {
        console.error('Failed to save consent:', e);
    }
}

export function initCookieConsent() {
    const dialog = document.getElementById('cookieConsentDialog') as HTMLDialogElement;
    const acceptBtn = document.getElementById('cookieConsentAccept') as HTMLButtonElement;
    const rejectBtn = document.getElementById('cookieConsentReject') as HTMLButtonElement;
    const saveBtn = document.getElementById('cookieConsentSave') as HTMLButtonElement;

    // Checkboxes
    const necessaryCheckbox = document.getElementById('necessary-cookies') as HTMLInputElement;
    const preferencesCheckbox = document.getElementById('preference-cookies') as HTMLInputElement;
    const statisticsCheckbox = document.getElementById('statistics-cookies') as HTMLInputElement;
    const marketingCheckbox = document.getElementById('marketing-cookies') as HTMLInputElement;

    if (!dialog || !acceptBtn || !rejectBtn || !saveBtn || !necessaryCheckbox || !preferencesCheckbox || !statisticsCheckbox || !marketingCheckbox) {
        console.error('Failed to initialize cookie consent dialog. Missing elements.');
        return;
    }

    // Set initial checkbox states based on remembered consent
    const rememberedConsent = getRememberedConsent();
    updateCheckboxes(rememberedConsent);

    const hasConsent = isConsentSet();
    if (!hasConsent) {
        dialog.showModal();
    } else {
        setMatomoConsent(rememberedConsent.statistics);
    }

    acceptBtn.addEventListener('click', () => {
        const consent : ConsentValues = {
            necessary: true,
            preferences: true,
            statistics: true,
            marketing: true,
        };
        updateCheckboxes(consent);
        saveConsent(consent);
        setMatomoConsent(true);
        dialog.close();
    });
    rejectBtn.addEventListener('click', () => {
        const consent : ConsentValues = {
            necessary: true,
            preferences: false,
            statistics: false,
            marketing: false,
        };
        updateCheckboxes(consent);
        saveConsent(consent);
        setMatomoConsent(false);
        dialog.close();
    });
    saveBtn.addEventListener('click', () => {
        const consent : ConsentValues = {
            necessary: true,
            preferences: preferencesCheckbox.checked,
            statistics: statisticsCheckbox.checked,
            marketing: marketingCheckbox.checked,
        };
        updateCheckboxes(consent);
        saveConsent(consent);
        setMatomoConsent(consent.statistics);
        dialog.close();
    })

    for (const btn of document.querySelectorAll<HTMLButtonElement>('[data-toggle="cookieConsent"]')) {
        btn.addEventListener('click', () => {
            dialog.showModal();
        });
    }

    function updateCheckboxes(consent : ConsentValues) : void {
        necessaryCheckbox.checked = true; // Always true for necessary cookies
        preferencesCheckbox.checked = consent.preferences;
        statisticsCheckbox.checked = consent.statistics;
        marketingCheckbox.checked = consent.marketing;
    }
}

