import {formatPhoneNumber, initAutoSaveForm, initCopyToClipboard, initTooltips} from './functions';
import axios from 'axios';
import route from "./router";
import initDatePickers from "./datePickers";
import {initClearButtons} from "./pages/utils";
import {Popover, Tab} from 'bootstrap';

axios.defaults.headers.post['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.get['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if ('serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker.register('/dist/service-worker.js', {scope: '/'}).then(registration => {
			console.log('SW registered: ', registration);
		}).catch(registrationError => {
			console.log('SW registration failed: ', registrationError);
		});
	});
}

window.addEventListener("load", () => {

	// Auto-format tel
	document.querySelectorAll('input[type="tel"]').forEach(input => {
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
	const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]')
	const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new Popover(popoverTriggerEl))

	// Toggles
	document.querySelectorAll('[data-toggle="submit"]').forEach(element => {
		element.addEventListener("change", () => {
			element.findParentElement("form").submit();
		});
	});
	document.querySelectorAll('[data-toggle="scroll-to"]').forEach(element => {
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

	document.querySelectorAll('[role="tablist"]').forEach(wrap => {
		const tabs = wrap.querySelectorAll('[data-bs-toggle="tab"]');
		console.log(wrap, window.location.hash);
		tabs.forEach(tabEl => {
			const target = tabEl.dataset.bsTarget ?? tabEl.getAttribute('href');
			const tab = Tab.getOrCreateInstance(tabEl);
			console.log(target, tab, tabEl);
			if (window.location.hash === target) {
				tab.show();
			}
			tabEl.addEventListener('shown.bs.tab', () => {
				window.location.hash = target;
			});
		});
	});

	initCopyToClipboard();

	// Pages
	route(page);
});