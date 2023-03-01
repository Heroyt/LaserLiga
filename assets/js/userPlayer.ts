import axios from "axios";
import {startLoading, stopLoading} from "./loaders";

export function initSetMe(wrapper: Element | Document = document): void {
	(wrapper.querySelectorAll('.setMe') as NodeListOf<HTMLButtonElement>).forEach(btn => {
		const id = btn.dataset.id;
		const system = btn.dataset.system;
		btn.addEventListener('click', () => {
			startLoading();
			axios.post('/user/player/setme', {id, system})
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
		const id = btn.dataset.group;
		btn.addEventListener('click', () => {
			startLoading();
			axios.post('/user/player/setmegroup', {id})
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