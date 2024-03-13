import {Popover, Tooltip} from "bootstrap";
import {startLoading, stopLoading} from "./loaders";
import {customFetch, FormSaveResponse, RequestMethod} from "./api/client";

declare global {
    const prettyUrl: boolean;

    interface String {
        replaceMultiple(chars: string[]): String

        decodeEntities(): String
    }

    interface Element {
        findParentElement(elemName: string): HTMLElement | null

        findParentElementByClassName(className: string): HTMLElement | null
    }

    interface Math {
        easeInOutQuad(t: number, b: number, c: number, d: number): number
    }

    interface Window {
        scrollSmooth(to: number, duration: number): void
    }
}

String.prototype.replaceMultiple = function (chars: string[]) {
    let retStr = this;
    chars.forEach(ch => {
        retStr = retStr.replace(new RegExp(ch[0], 'g'), ch[1]);
    });
    return retStr;
};
String.prototype.decodeEntities = function () {
    const element = document.createElement('div');
    let str = this;
    str = str.replace(/<script[^>]*>([\S\s]*?)<\/script>/gmi, '');
    str = str.replace(/<\/?\w(?:[^"'>]|"[^"]*"|'[^']*')*>/gmi, '');
    element.innerHTML = str;
    str = element.textContent;
    element.textContent = '';
    return str;
}
/**
 * Finds a parent element
 *
 * @param elemName {String}
 */
Element.prototype.findParentElement = function (elemName: string) {
    let currElem = this;
    while (currElem.tagName.toLowerCase() !== elemName.toLowerCase()) {
        currElem = currElem.parentNode;
        if (currElem === document.body) {
            return null;
        }
    }
    return currElem;
}

Element.prototype.findParentElementByClassName = function (className: string): HTMLElement | null {
    let currElem = this;
    while (!currElem.classList.contains(className)) {
        currElem = currElem.parentNode;
        if (currElem === document.body) {
            return null;
        }
    }
    return currElem;
}

/**
 * @param {number} t Current time
 * @param {number} b Start time
 * @param {number} c Change in value
 * @param {number} d Duration
 *
 * @return {number}
 */
Math.easeInOutQuad = function (t: number, b: number, c: number, d: number): number {
    t /= d / 2;
    if (t < 1) return c / 2 * t * t + b;
    t--;
    return -c / 2 * (t * (t - 2) - 1) + b;
};

/**
 * Smooth scroll element to y value
 *
 * @param {number} to Pixel value from top
 * @param {number} duration Time in ms
 */
window.scrollSmooth = function (to: number, duration: number) {
    let start = window.scrollY, change = to - start, currentTime = 0, increment = 10;

    const animateScroll = function () {
        currentTime += increment;
        // @ts-ignore
        window.scrollBy(0, Math.easeInOutQuad(currentTime, start, change, duration) - window.scrollY)
        if (currentTime < duration) {
            setTimeout(animateScroll, increment);
        }
    };
    animateScroll();
}

/**
 * Format a phone number to `000 000 000` format
 * @param {string} str
 * @returns {string|null}
 */
export function formatPhoneNumber(str: string): string | null {
    //Filter only numbers from the input
    const plus = str[0] === '+';
    const cleaned = ('' + str).replace(/\D/g, '');
    // Get all numbers as an array
    const numbers = cleaned.split('');
    if (numbers.length > 0) {
        // Build pattern
        return (plus ? '+' : '') + numbers.slice(0, 3).join('') + ' ' + numbers.slice(3, 6).join('') + ' ' + numbers.slice(6, 9).join('') + ' ' + numbers.slice(9, 12).join('');
    }
    return null
}

/**
 * Check if the email is valid
 *
 * @param {string} email
 * @returns {boolean}
 */
export function validateEmail(email: string): boolean {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

/**
 * Get the whole URL to given request
 *
 * @param {string[]} request
 *
 * @returns {string}
 */
export function getLink(request: string[]): string {
    if (prettyUrl) {
        return window.location.origin + '/' + request.join('/');
    }
    let query = {
        lang: document.documentElement.lang
    };
    let i = 0;
    request.forEach(page => {
        if (page === '') {
            return;
        }
        // @ts-ignore
        query[`p[${i}]`] = page;
        i++;
    });
    const params = new URLSearchParams(query);
    return window.location.origin + "?" + params.toString();
}

/**
 * Setup select elements that have additional description
 *
 * @param {Element} input
 */
export function selectInputDescriptionSetup(input: HTMLSelectElement) {
    const id = input.id;
    const descriptionElement = document.querySelectorAll(`.select-description[data-target="#${id}"]`);
    const update = () => {
        const val = input.value;
        const description = (input.querySelector(`option[value="${val}"]`) as HTMLOptionElement).dataset.description;
        descriptionElement.forEach(elem => {
            elem.innerHTML = description;
        });
    };
    if (descriptionElement) {
        update();
        input.addEventListener("change", update);
    }
}

export function initTooltips(dom: HTMLElement | Document) {
    const tooltipTriggerList = [].slice.call(dom.querySelectorAll('[data-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl: HTMLElement) {
        return new Tooltip(tooltipTriggerEl)
    });
}

export function initPopovers(dom: HTMLElement | Document) {
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]') as NodeListOf<HTMLElement>;
    [...popoverTriggerList].map(popoverTriggerEl => new Popover(popoverTriggerEl));
}

export function initAutoSaveForm() {
    // Autosave form
    (document.querySelectorAll('form.autosave') as NodeListOf<HTMLFormElement>).forEach(form => {
        const method = form.method.toUpperCase() as RequestMethod;
        const url = form.action;

        let lastData = new FormData(form);
        let autosaving = 0;
        const lastSave = document.querySelectorAll(`.last-save[data-target="#${form.id}"]`) as NodeListOf<HTMLDivElement>;
        const saveButtons = form.querySelectorAll(`[data-action="autosave"]`) as NodeListOf<HTMLButtonElement>;
        const save = (smallLoader = true) => {
            let newData = new FormData(form);
            let changed = false;
            if (!smallLoader) {
                startLoading(false);
            }
            newData.forEach((value, key) => {
                if (changed || key === "_csrf_token" || key === 'action') {
                    return;
                }
                if (!lastData.has(key)) {
                    console.log("Changed - new key", key, value)
                    changed = true;
                } else if (value instanceof File) {
                    if (value.name !== (lastData.get(key) as File).name) {
                        console.log("Changed - new file", key, value)
                        changed = true;
                    }
                } else if (JSON.stringify(lastData.getAll(key)) !== JSON.stringify(newData.getAll(key))) {
                    console.log("Changed - new value", key, value)
                    changed = true;
                }
            });
            if (!changed) {
                lastData.forEach((value, key) => {
                    if (changed || key === "_csrf_token" || key === 'action') {
                        return;
                    }
                    if (!newData.has(key)) {
                        console.log("Changed - removed key", key, value)
                        changed = true;
                    }
                });
            }
            if (changed && autosaving === 0) {
                autosaving++;
                lastData = newData;
                newData.append("action", "autosave");
                if (smallLoader) startLoading(smallLoader);
                saveButtons.forEach(button => {
                    button.disabled = true;
                });
                customFetch(url, method, {body: newData})
                    .then((result: FormSaveResponse) => {
                        autosaving--;
                        stopLoading(result.success, smallLoader);
                        saveButtons.forEach(button => {
                            button.disabled = false;
                        });
                        lastSave.forEach(save => {
                            save.innerHTML = (new Date()).toLocaleTimeString();
                        });
                    })
                    .catch(err => {
                        console.error(err);
                        autosaving--;
                        stopLoading(false, smallLoader);
                        saveButtons.forEach(button => {
                            button.disabled = false;
                        });
                    });
            } else if (!smallLoader) {
                stopLoading(true, false);
                lastSave.forEach(save => {
                    save.innerHTML = (new Date()).toLocaleTimeString();
                });
            }
        };

        form.addEventListener("autosave", () => save());

        saveButtons.forEach(button => {
            button.addEventListener("click", e => {
                if (button.dataset.prevent) {
                    e.preventDefault();
                }
                save(false);
            });
        })

        setInterval(save, 10000);
    });
}

export function initCopyToClipboard(elem: HTMLElement | null | Document = null): void {
    if (!elem) {
        elem = document;
    }
    (elem.querySelectorAll('[data-action="copy-to-clipboard"]') as NodeListOf<HTMLButtonElement>).forEach(triggerElem => {
        const targetSelector = triggerElem.dataset.target ?? '';
        if (!targetSelector) {
            return;
        }
        const targetElement = document.querySelector(targetSelector) as HTMLInputElement | null;
        if (!targetElement || targetElement.nodeName !== 'INPUT') {
            return;
        }
        triggerElem.addEventListener("click", () => {
            targetElement.select();
            targetElement.setSelectionRange(0, 99999);

            navigator.clipboard.writeText(targetElement.value);
            console.log('Copy', targetElement.value);
        });
    });
}

export function initCheckAll(elem: HTMLElement | null | Document = null): void {
    if (!elem) {
        elem = document;
    }
    (elem.querySelectorAll('[data-action="check-all"]') as NodeListOf<HTMLElement>).forEach(triggerElem => {
        if (!(triggerElem instanceof HTMLInputElement)) {
            return;
        }
        const targetSelector = triggerElem.dataset.target ?? '';
        if (!targetSelector) {
            return;
        }
        const targetElements = document.querySelectorAll(targetSelector) as NodeListOf<HTMLInputElement>;

        const uncheckSelector = triggerElem.dataset.uncheck ?? '';
        const uncheckElements: NodeListOf<HTMLInputElement> | HTMLInputElement[] = uncheckSelector ? document.querySelectorAll(uncheckSelector) as NodeListOf<HTMLInputElement> : [];
        console.log(triggerElem, 'target', targetElements, 'uncheck', uncheckElements);

        triggerElem.addEventListener('change', () => {
            targetElements.forEach(target => {
                if (target.disabled) {
                    return;
                }
                target.checked = triggerElem.checked;
            });
            if (uncheckElements.length > 0) {
                uncheckElements.forEach(elem => {
                    if (elem.disabled) {
                        return;
                    }
                    elem.checked = !triggerElem.checked;
                });
            }
        });

        targetElements.forEach(target => {
            target.addEventListener('change', () => {
                triggerElem.checked = isAllChecked();
            });
        });

        function isAllChecked(): boolean {
            for (let i = 0; i < targetElements.length; i++) {
                if (!targetElements[i].checked) {
                    return false;
                }
            }
            return true;
        }
    });
}

export function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export function initTableRowLink(elem: HTMLElement | null | Document = null): void {
    if (!elem) {
        elem = document;
    }

    const links: NodeListOf<HTMLTableRowElement | HTMLDivElement> = elem.querySelectorAll('tr[data-href], .linkable[data-href]');
    for (const row of links) {
        row.addEventListener('click', e => {
            if (e.target instanceof HTMLAnchorElement || e.target instanceof HTMLButtonElement) {
                return;
            }
            window.location.href = row.dataset.href;
        })
    }
}
