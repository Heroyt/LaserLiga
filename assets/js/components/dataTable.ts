import initDatePickers from "../datePickers";
import {startLoading, stopLoading} from "../loaders";
import {initCheckAll, initTableRowLink, initTooltips} from "../functions";
import {initClearButtons} from "../pages/utils";
import {fetchGet} from "../api/client";

export function initDataTableForm(form: HTMLFormElement, afterUpdate: (() => void) | null = null) {
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
        initTableRowLink(form);

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
        const sortByMobile: HTMLSelectElement = form.querySelector('#sortByMobile');
        const sortDirectionMobile: NodeListOf<HTMLInputElement> = form.querySelectorAll('[name="mobileOrderDirection"]');
		(form.querySelectorAll('.sortable') as NodeListOf<HTMLTableHeaderCellElement>)
			.forEach((cell) => {
				const name = cell.dataset.name;

				cell.addEventListener('click', () => {
                    sortByMobile.value = name;
					orderByInput.value = name;
					dirInput.value = cell.classList.contains('sort-asc') ? 'desc' : 'asc';
                    for (const input of sortDirectionMobile) {
                        input.checked = input.value === dirInput.value;
                    }
                    updateTable();
                });
            });
        if (sortByMobile) {
            sortByMobile.addEventListener('change', () => {
                orderByInput.value = sortByMobile.value;
                updateTable();
            });
        }
        for (const input of sortDirectionMobile) {
            input.addEventListener('change', () => {
                dirInput.value = input.value;
                updateTable();
			});
        }

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
        const limit = form.querySelector('#limit') as HTMLSelectElement;
        if (limit) {
            limit.addEventListener('change', () => {
                pageInput.value = '1';
                updateTable();
            });
        }

		// Scroll to searched row
		const row = form.querySelector('tr.table-success') as HTMLTableRowElement | null
		if (!row) {
			return;
		}
		window.scrollTo(0, row.getBoundingClientRect().y + window.scrollY - 100)
	}

	function updateTable() {
		const data = new FormData(form);
        let query = new URLSearchParams;

		data.forEach((value, name) => {
            query.append(name, value.toString());
		});

		startLoading(true);
        fetchGet(form.action, query)
            .then((response: string) => {
                if (!document.startViewTransition) {
                    processResponse(response);
                }
                else {
                    document.startViewTransition(() => {
                        processResponse(response);
                    })
                }
			})
			.catch(e => {
				console.error(e);
				stopLoading(false, true);
			})

        function processResponse(response: string) {
            const tmp = document.createElement('div');
            tmp.innerHTML = response;
            const tmpForm = tmp.querySelector<HTMLFormElement>(`form#${form.id}`);
            if (tmpForm) {
                response = tmpForm.innerHTML;
            }
            form.innerHTML = response;
            if (updateHistory) {
                window.history.pushState({}, '', form.action + (query.size > 0 ? '?' + query.toString() : ''));
            }
            initForm();
            stopLoading(true, true);
            if (afterUpdate) {
                afterUpdate();
            }
        }
	}
}