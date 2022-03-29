import {Tooltip} from "bootstrap";
import {startLoading, stopLoading} from "./loaders";
import axios from "axios";

String.prototype.replaceMultiple = function (chars) {
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
Element.prototype.findParentElement = function (elemName) {
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
Element.prototype.findParentElementByClassName = function (className) {
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
Math.easeInOutQuad = function (t, b, c, d) {
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
window.scrollSmooth = function (to, duration) {
	let start = window.scrollY,
		change = to - start,
		currentTime = 0,
		increment = 10;

	const animateScroll = function () {
		currentTime += increment;
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
export function formatPhoneNumber(str) {
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
export function validateEmail(email) {
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
export function getLink(request) {
	if (prettyUrl) {
		return window.location.origin + '/' + document.documentElement.lang + '/' + request.join('/');
	} else {
		let query = {
			lang: document.documentElement.lang
		};
		let i = 0;
		request.forEach(page => {
			if (page === '') {
				return;
			}
			query[`p[${i}]`] = page;
			i++;
		});
		const params = new URLSearchParams(query);
		return window.location.origin + "?" + params.toString();
	}
}

/**
 * Setup select elements that have additional description
 *
 * @param {Element} input
 */
export function selectInputDescriptionSetup(input) {
	const id = input.id;
	const descriptionElement = document.querySelectorAll(`.select-description[data-target="#${id}"]`);
	const update = () => {
		const val = input.value;
		const description = input.querySelector(`option[value="${val}"]`).dataset.description;
		descriptionElement.forEach(elem => {
			elem.innerHTML = description;
		});
	};
	if (descriptionElement) {
		update();
		input.addEventListener("change", update);
	}
}

export function initTooltips(dom) {
	const tooltipTriggerList = [].slice.call(dom.querySelectorAll('[data-toggle="tooltip"]'))
	const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
		return new Tooltip(tooltipTriggerEl)
	});
}

export function initAutoSaveForm() {
	// Autosave form
	document.querySelectorAll('form.autosave').forEach(form => {
		const method = form.method;
		const url = form.action;

		let lastData = new FormData(form);
		let autosaving = 0;
		const lastSave = document.querySelectorAll(`.last-save[data-target="#${form.id}"]`);
		const saveButtons = form.querySelectorAll(`[data-action="autosave"]`);
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
					if (value.name !== lastData.get(key).name) {
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

		form.addEventListener("autosave", save);

		saveButtons.forEach(button => {
			button.addEventListener("click", () => {
				save(false);
			});
		})

		setInterval(save, 10000);
	});
}