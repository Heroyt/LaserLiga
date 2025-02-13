import flatpickr from "flatpickr";
import {Options} from "flatpickr/dist/types/options.js";
import LocaleKey = flatpickr.Options.LocaleKey;

export default function initDatePickers(elem: HTMLElement | HTMLDocument = null) {
    if (!elem) {
        elem = document;
    }

    import(
        /* webpackChunkName: "flatpickr-l10n" */
        `flatpickr/dist/l10n/cs`
        ).then(localizationModule => {
        const lang = localizationModule.default['cs'];
        flatpickr.localize(lang);

        (elem.querySelectorAll('input[type="date"]:not([data-input]), .date-picker') as NodeListOf<HTMLInputElement | HTMLDivElement>).forEach(input => {
            let value = '', wrap = !(input instanceof HTMLInputElement);
            if (wrap) {
                value = (input.querySelector("[data-input]") as HTMLInputElement).value;
            } else {
                // @ts-ignore
                value = input.value;
			}
			let options: Options = {
				defaultDate: value,
				dateFormat: "d.m.Y",
				position: "auto center",
				positionElement: input,
				static: true,
				appendTo: input.parentElement,
                allowInput: true,
                locale: document.documentElement.lang as LocaleKey,
				wrap,
			};
			if (input.dataset.events) {
				const events = JSON.parse(input.dataset.events);
				options.enable = Object.keys(events);
			}
			if (input.dataset.max) {
				options.maxDate = input.dataset.max;
			}
			if (input.dataset.min) {
				options.minDate = input.dataset.min;
			}
			flatpickr(input, options);
		});
		(elem.querySelectorAll('input[type="datetime"]:not([data-input]), .datetime-picker') as NodeListOf<HTMLInputElement | HTMLDivElement>).forEach(input => {
			let value = '', wrap = !(input instanceof HTMLInputElement);
			if (wrap) {
				value = (input.querySelector("[data-input]") as HTMLInputElement).value;
			} else {
				// @ts-ignore
				value = input.value;
			}
			let options: Options = {
				defaultDate: value,
				dateFormat: "d.m.Y H:i",
				position: "auto center",
				positionElement: input,
				enableTime: true,
				time_24hr: true,
				appendTo: input.parentElement,
                allowInput: true,
                locale: document.documentElement.lang as LocaleKey,
				wrap: wrap,
			};
			if (input.dataset.max) {
				options.maxDate = input.dataset.max;
			}
			if (input.dataset.min) {
				options.minDate = input.dataset.min;
			}
			flatpickr(input, options);
		});
		(elem.querySelectorAll('input[type="time"]:not([data-input]), .time-picker') as NodeListOf<HTMLInputElement | HTMLDivElement>).forEach(input => {
			let value = '', wrap = !(input instanceof HTMLInputElement);
			if (wrap) {
				value = (input.querySelector("[data-input]") as HTMLInputElement).value;
			} else {
				// @ts-ignore
				value = input.value;
			}
			let options: Options = {
				defaultDate: value,
				dateFormat: "H:i",
				position: "auto center",
				positionElement: input,
				enableTime: true,
				noCalendar: true,
				time_24hr: true,
				appendTo: input.parentElement,
                allowInput: true,
                locale: document.documentElement.lang as LocaleKey,
				wrap: wrap,
				onOpen: (e) => {
					elem.querySelectorAll('.numInput').forEach((pickerInput: HTMLInputElement) => {
						pickerInput.name = "flatpickr[]";
						pickerInput.type = "number";
					});
				},
				onClose: (e) => {
					elem.querySelectorAll('.numInput').forEach((pickerInput: HTMLInputElement) => {
						pickerInput.type = "text";
					});
				}
			};
			if (input.dataset.max) {
				options.maxDate = input.dataset.max;
			}
			if (input.dataset.min) {
				options.minDate = input.dataset.min;
			}
			flatpickr(input, options);
		});
	});
}