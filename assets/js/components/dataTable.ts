import initDatePickers from "../datePickers";
import {startLoading, stopLoading} from "../loaders";
import axios, {AxiosResponse} from "axios";
import {initCheckAll, initTooltips} from "../functions";
import {initClearButtons} from "../pages/utils";

export function initDataTableForm(form: HTMLFormElement) {
	const updateHistory = (form.dataset.noHistory ?? '') !== '1';

	form.addEventListener('submit', e => {
		e.preventDefault();
		updateTable();
	})

	initForm();

	function initForm() {
		const pageInput = document.getElementById('inputPage') as HTMLInputElement;
		const orderByInput = document.getElementById('inputOrderBy') as HTMLInputElement;
		const dirInput = document.getElementById('inputDir') as HTMLInputElement;

		initCheckAll(form);
		initDatePickers(form);
		initTooltips(form);
		initClearButtons(form);

		// Type select
		const typeInput = document.getElementById('inputActiveType') as HTMLInputElement;
		if (typeInput) {
			(form.querySelectorAll('.table-type-select') as NodeListOf<HTMLAnchorElement>)
				.forEach(link => {
					const type = link.dataset.type;
					link.addEventListener('click', () => {
						typeInput.value = type;
						updateTable();
					});
				});
		}

		// Sorting
		(form.querySelectorAll('.sortable') as NodeListOf<HTMLTableHeaderCellElement>)
			.forEach((cell) => {
				const name = cell.dataset.name;

				cell.addEventListener('click', () => {
					orderByInput.value = name;
					dirInput.value = cell.classList.contains('sort-asc') ? 'desc' : 'asc';
					updateTable();
				});
			});

		// Pagination
		(form.querySelectorAll('.page-item:not(.disabled) .page-link, .page-link-standalone') as NodeListOf<HTMLAnchorElement>)
			.forEach(link => {
				const p = link.dataset.page;

				link.addEventListener('click', e => {
					e.preventDefault();
					pageInput.value = p;
					const search = form.querySelector(`input[name="search"]`) as HTMLInputElement | null;
					if (search) {
						search.value = '';
					}
					updateTable();
				});
			});
		(form.querySelector('#limit') as HTMLSelectElement).addEventListener('change', () => {
			pageInput.value = '1';
			updateTable();
		});

		// Scroll to searched row
		const row = form.querySelector('tr.table-success') as HTMLTableRowElement | null
		if (!row) {
			return;
		}
		window.scrollTo(0, row.getBoundingClientRect().y + window.scrollY - 100)
	}

	function updateTable() {
		const data = new FormData(form);
		let query: string[] = [];

		data.forEach((value, name) => {
			query.push(name + '=' + value);
		});

		startLoading(true);
		const url = form.action + (query.length > 0 ? '?' + query.join('&') : '');
		axios.get(url)
			.then((response: AxiosResponse<string>) => {
				form.innerHTML = response.data;
				if (updateHistory) {
					window.history.pushState({}, '', url);
				}
				initForm();
				stopLoading(true, true);
			})
			.catch(e => {
				console.error(e);
				stopLoading(false, true);
			})
	}
}