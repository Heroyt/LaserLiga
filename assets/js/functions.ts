import {Tooltip} from "bootstrap";
import {startLoading, stopLoading} from "./loaders";
import axios from "axios";

declare global {
	const prettyUrl: boolean;
}

// @ts-ignore
String.prototype.replaceMultiple = function (chars: string[]) {
	let retStr = this;
	chars.forEach(ch => {
		retStr = retStr.replace(new RegExp(ch[0], 'g'), ch[1]);
	});
	return retStr;
};
// @ts-ignore
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
// @ts-ignore
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
/**
 * Finds a parent element
 *
 * @param className {String}
 *
 * @return {Element}
 */
// @ts-ignore
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
// @ts-ignore
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
// @ts-ignore
window.scrollSmooth = function (to: number, duration: number) {
	let start = window.scrollY,
		change = to - start,
		currentTime = 0,
		increment = 10;

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

export function initTooltips(dom: HTMLElement) {
	const tooltipTriggerList = [].slice.call(dom.querySelectorAll('[data-toggle="tooltip"]'))
	tooltipTriggerList.map(function (tooltipTriggerEl: HTMLElement) {
		return new Tooltip(tooltipTriggerEl)
	});
}

export function initAutoSaveForm() {
	// Autosave form
	(document.querySelectorAll('form.autosave') as NodeListOf<HTMLFormElement>).forEach(form => {
		const method = form.method;
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
				axios(
					{
						method,
						url,
						data: newData
					}
				)
					.then((result) => {
						autosaving--;
						stopLoading(result.data.success, smallLoader);
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
			}
		};

		form.addEventListener("autosave", () => {
			save();
		});

		saveButtons.forEach(button => {
			button.addEventListener("click", () => {
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