export function initClearButtons(wrapper: Document | Element = document): void {
	const btns = wrapper.querySelectorAll('[data-toggle="clear"]') as NodeListOf<HTMLButtonElement>;
	btns.forEach(btn => {
		const targets = document.querySelectorAll(btn.dataset.target) as NodeListOf<HTMLInputElement | HTMLSelectElement | HTMLFormElement>;
		btn.addEventListener('click', () => {
			targets.forEach(target => {
				if (target instanceof HTMLFormElement) {
					target.reset();
				} else if (target instanceof HTMLInputElement || target instanceof HTMLSelectElement) {
					target.value = target.dataset.default ?? '';
				}
			});
		});
	});
}