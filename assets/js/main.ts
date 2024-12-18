import {
    formatPhoneNumber,
    initAutoSaveForm,
    initCopyToClipboard,
    initPopovers,
    initTableRowLink,
    initTooltips,
    selectInputDescriptionSetup
} from './functions';
import route from "./router";
import initDatePickers from "./datePickers";
import {initClearButtons} from "./pages/utils";
import {Tab} from 'bootstrap';
import {checkPush, registerPush, updatePush} from "./push";
import {startLoading, stopLoading} from "./loaders";
import {userSendNewConfirmEmail} from "./api/endpoints/user";
import {triggerNotification} from "./components/notifications";

declare global {
    const usr: number | null;
    const assetVersion: number;
    const _mtm: any[];
    const _paq: any[][];
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(`/dist/service-worker.js?v=${assetVersion}`, {scope: '/'})
            .then(registration => {
                console.log('SW registered: ', registration);
                if (!('PushManager' in window)) {
                    console.warn('Push manager is not supported');
                    return registration;
                }

                // Check user change
                const currUser: string | null = localStorage.getItem("currUser");
                let userChanged = false;
                if (usr !== null && currUser !== usr.toString()) {
                    userChanged = true;
                    localStorage.setItem("currUser", usr.toString());
                }

                if (Notification.permission === "default" && usr !== null) {
                    Notification.requestPermission()
                        .then(async result => {
                            if (result === 'denied') {
                                console.error('The user explicitly denied the permission request.');
                                return;
                            }
                            if (result === 'granted') {
                                console.info('The user accepted the permission request.');

                                const subscribed = await registration.pushManager.getSubscription();
                                if (!subscribed) {
                                    await registerPush(registration);
                                } else {
                                    await checkPush(subscribed);
                                }
                            }
                        })
                        .catch(() => {

                        });
                } else if (Notification.permission === 'granted') {
                    registration.pushManager.getSubscription().then(async (subscription) => {
                        if (subscription) {
                            await checkPush(subscription);
                            if (userChanged) {
                                await updatePush(subscription);
                            }
                        }
                    })
                }
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

window.addEventListener("load", () => {
    // Auto-format tel
    (document.querySelectorAll('input[type="tel"]') as NodeListOf<HTMLInputElement>).forEach(input => {
        if (input.classList.contains('not-format')) {
            return;
        }
        input.value = formatPhoneNumber(input.value);
        input.addEventListener("keydown", () => {
            input.value = formatPhoneNumber(input.value);
        });
        input.addEventListener("change", () => {
            input.value = formatPhoneNumber(input.value);
        });
    });

    // Utils
    initClearButtons();

    // Datepicker
    initDatePickers();

    // Tooltips
    initTooltips(document);

    // Auto-save
    initAutoSaveForm();

    // Popovers
    initPopovers(document);

    // Table row links
    initTableRowLink(document);

    // Toggles
    (document.querySelectorAll('[data-toggle="submit"]') as NodeListOf<HTMLButtonElement>).forEach(element => {
        element.addEventListener("change", () => {
            (element.findParentElement("form") as HTMLFormElement).submit();
        });
    });
    (document.querySelectorAll('[data-toggle="scroll-to"]') as NodeListOf<HTMLElement>).forEach(element => {
        const delay = parseInt(element.dataset.delay ?? "0");
        element.addEventListener('click', () => {
            setTimeout(() => {
                const target = document.querySelector(element.dataset.target);
                if (!target) {
                    return;
                }
                window.scrollTo(0, target.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop));
            }, delay);
        });
    });

    // Pull to load
    let _startY = 0;
    document.addEventListener('touchstart', e => {
        _startY = e.touches[0].pageY;
    }, {passive: true});

    document.addEventListener('touchend', e => {
        if (document.body.classList.contains('refreshing')) {
            window.location.reload();
        }
    });

    document.addEventListener('touchmove', e => {
        const y = e.touches[0].pageY;
        console.log(y, _startY);
        // Activate custom pull-to-refresh effects when at the top fo the container
        // and user is scrolling up.
        if (document.scrollingElement.scrollTop === 0 && y > _startY && !document.body.classList.contains('refreshing')) {
            document.body.classList.add('refreshing');
        } else if (document.body.classList.contains('refreshing') && y > 0) {
            document.body.classList.remove('refreshing');
        }
    }, {passive: true});

    // Tabs activate
    (document.querySelectorAll('[role="tablist"]') as NodeListOf<HTMLDivElement>).forEach(wrap => {
        const tabs = wrap.querySelectorAll('[data-bs-toggle="tab"]') as NodeListOf<HTMLElement>;
        console.log(wrap, window.location.hash);
        const params = new URLSearchParams(window.location.search);
        const currentTab = params.get('tab');
        tabs.forEach(tabEl => {
            const target = tabEl.dataset.bsTarget ?? tabEl.getAttribute('href');
            const tab = Tab.getOrCreateInstance(tabEl);
            console.log(target, tab, tabEl);
            if (currentTab === target || window.location.hash === target) {
                setTimeout(() => {
                    tab.show();
                }, 500);
            }
            tabEl.addEventListener('shown.bs.tab', () => {
                const url = new URL(tabEl.dataset.link ? tabEl.dataset.link : window.location.href);
                if (!tabEl.dataset.link) {
                    url.searchParams.set('tab', target.replace('#', ''));
                }
                window.history.replaceState(undefined, '', url)
                //window.location.hash = target;
            });
        });
    });

    initCopyToClipboard();

    document.querySelectorAll<HTMLSelectElement>('select[data-trigger-description]').forEach(selectInputDescriptionSetup)

    // Mobile nav
    const mainNav = document.getElementById('mobile-menu-full') as HTMLDivElement;
    const toggleMainNav = document.getElementById('triggerMainNav') as HTMLButtonElement;
    if (mainNav && toggleMainNav) {
        const closeBtns = mainNav.querySelectorAll<HTMLElement>('.btn-close, [data-trigger="close"]');
        let mainNavActive = true;
        let mainNavThrottle: NodeJS.Timeout = null;
        toggleMainNav.addEventListener('click', () => {
            if (!mainNavActive) {
                return;
            }
            mainNavActive = false;
            mainNavThrottle = setTimeout(() => mainNavActive = true, 50);
            mainNav.classList.toggle('show');
            toggleMainNav.classList.toggle('show');
        });
        for (const closeBtn of closeBtns) {
            closeBtn.addEventListener('click', () => {
                mainNav.classList.remove('show');
                toggleMainNav.classList.remove('show');
            });
        }
    }

    // Share buttons
    if (navigator.share) {
        (document.querySelectorAll('[data-trigger="share"]') as NodeListOf<HTMLButtonElement>).forEach(btn => {
            const title = btn.dataset.title ?? document.title;
            const text = btn.dataset.text ?? '';
            const url = btn.dataset.url ?? window.location.href;
            let shareData: ShareData = {};
            if (title !== '') {
                shareData.title = title;
            }
            if (text !== '') {
                shareData.text = text;
            }
            if (url !== '') {
                shareData.url = url;
            }

            btn.classList.remove('d-none');

            btn.addEventListener('click', async () => {
                _mtm.push({share: `${title} - ${text} (${url})`});
                _paq.push(['trackEvent', 'Interaction', 'Share', title, url]);
                await navigator.share(shareData);
            });
        });
    }

    const confirmEmail = document.getElementById('confirmEmail') as HTMLButtonElement;
    if (confirmEmail) {
        confirmEmail.addEventListener('click', () => {
            startLoading(true);
            userSendNewConfirmEmail()
                .then(res => {
                    stopLoading(true, true);
                    triggerNotification({
                        content: res.message,
                        type: 'success',
                    })
                })
                .catch(e => {
                    stopLoading(false, true);
                    triggerNotification(e);
                })
        })
    }

    // Pages
    route(page);

    window.triggerNotification = triggerNotification;
});