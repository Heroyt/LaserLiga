import {startLoading, stopLoading} from "./loaders";
import {userSetGroupMe, userSetMe} from "./api/endpoints/user";

export function initSetMe(wrapper: Element | Document = document): void {
	(wrapper.querySelectorAll('.setMe') as NodeListOf<HTMLButtonElement>).forEach(btn => {
        const id = parseInt(btn.dataset.id);
		const system = btn.dataset.system;
		btn.addEventListener('click', () => {
			startLoading();
            userSetMe(id, system)
				.then(() => {
					stopLoading(true);
					btn.remove();
				})
				.catch(e => {
					console.error(e);
					stopLoading(false);
				});
		});
	});
}

export function initSetMeGroup(wrapper: Element | Document = document): void {
	(wrapper.querySelectorAll('.setGroupMe') as NodeListOf<HTMLButtonElement>).forEach(btn => {
        const id = parseInt(btn.dataset.group);
		btn.addEventListener('click', () => {
			startLoading();
            userSetGroupMe(id)
				.then(() => {
					stopLoading(true);
					btn.remove();
				})
				.catch(e => {
					console.error(e);
					stopLoading(false);
				});
		});
	});
}