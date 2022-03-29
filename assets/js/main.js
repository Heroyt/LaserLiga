import {formatPhoneNumber, initAutoSaveForm, initTooltips} from './functions.js';
import axios from 'axios';
import flatpickr from "flatpickr";

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
	document.querySelectorAll('input[type="date"]:not([data-input]), .date-picker').forEach(input => {
		let value = '', wrap = input.classList.contains("date-picker");
		if (wrap) {
			value = input.querySelector("[data-input]").value;
		} else {
			value = input.value;
		}
		let options = {
			defaultDate: value,
			dateFormat: "d.m.Y",
			position: "auto center",
			positionElement: input,
			static: true,
			appendTo: input.parentNode,
			wrap,
		};
		if (input.dataset.events) {
			const events = JSON.parse(input.dataset.events);
			options.enable = Object.keys(events);
		}
		flatpickr(input, options);
	});
	document.querySelectorAll('input[type="datetime"]:not([data-input]), .datetime-picker').forEach(input => {
		let value = '', wrap = input.classList.contains("datetime-picker");
		if (wrap) {
			value = input.querySelector("[data-input]").value;
		} else {
			value = input.value;
		}
		flatpickr(input, {
			defaultDate: value,
			dateFormat: "d.m.Y H:i",
			position: "auto center",
			positionElement: input,
			enableTime: true,
			time_24hr: true,
			appendTo: input.parentNode,
			wrap: wrap,
		});
	});
	document.querySelectorAll('input[type="time"]:not([data-input]), .time-picker').forEach(input => {
		let value = '', wrap = input.classList.contains("time-picker");
		if (wrap) {
			value = input.querySelector("[data-input]").value;
		} else {
			value = input.value;
		}
		flatpickr(input, {
			defaultDate: value,
			dateFormat: "H:i",
			position: "auto center",
			positionElement: input,
			enableTime: true,
			noCalendar: true,
			time_24hr: true,
			appendTo: input.parentNode,
			wrap: wrap,
			onOpen: (e) => {
				document.querySelectorAll('.numInput').forEach((pickerInput) => {
					pickerInput.name = "flatpickr[]";
					pickerInput.type = "number";
				});
			},
			onClose: (e) => {
				document.querySelectorAll('.numInput').forEach((pickerInput) => {
					pickerInput.type = "text";
				});
			}
		});
	});
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
});