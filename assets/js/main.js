import {formatPhoneNumber, initAutoSaveForm, initCopyToClipboard, initTooltips} from './functions';
import axios from 'axios';
import route from "./router";
import initDatePickers from "./datePickers";
import {initClearButtons} from "./pages/utils";

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
	})

	initCopyToClipboard();

	// Pages
	route(page);
});