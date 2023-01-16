import {formatPhoneNumber, initAutoSaveForm, initCopyToClipboard, initTooltips} from './functions';
import axios from 'axios';
import route from "./router";
import initDatePickers from "./datePickers";

axios.defaults.headers.post['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.get['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

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

	initCopyToClipboard();

	// Pages
	route(page);
});