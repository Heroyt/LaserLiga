import {formatPhoneNumber, initAutoSaveForm, initCopyToClipboard, initTooltips} from './functions';
import axios from 'axios';
import route from "./router";
import initDatePickers from "./datePickers";
import {initClearButtons} from "./pages/utils";
import {Popover, Tab} from 'bootstrap';
import {registerPush, updatePush} from "./push";

declare global {
	const usr: number | null;
	const assetVersion: number;
}

axios.defaults.headers.post['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.get['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if ('serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker.register(`/dist/service-worker.js?v=${assetVersion}`, {scope: '/'})
			.then(registration => {
				console.log('SW registered: ', registration);
				if (!('PushManager' in window)) {
					console.warn('Push manager is not supported');
					return registration;
				}

				// Check user change
				const currUser: string | null = localStorage.getItem("currUser");
				let userChanged = false;
				if (usr !== null && currUser !== usr.toString()) {
					userChanged = true;
					localStorage.setItem("currUser", usr.toString());
				}

				if (Notification.permission === "default" && usr !== null) {
					Notification.requestPermission()
						.then(async result => {
							if (result === 'denied') {
								console.error('The user explicitly denied the permission request.');
								return;
							}
							if (result === 'granted') {
								console.info('The user accepted the permission request.');

								const subscribed = await registration.pushManager.getSubscription();
								if (!subscribed) {
									await registerPush(registration);
								}
							}
						})
						.catch(() => {

						});
				} else if (Notification.permission === 'granted' && userChanged) {
					registration.pushManager.getSubscription().then(subscription => {
						if (subscription) {
							// noinspection JSIgnoredPromiseFromCall
							updatePush(subscription);
						}
					})
				}
			})
			.catch(registrationError => {
				console.log('SW registration failed: ', registrationError);
			});
	});
}

window.addEventListener("load", () => {
	// Auto-format tel
	(document.querySelectorAll('input[type="tel"]') as NodeListOf<HTMLInputElement>).forEach(input => {
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
	const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]') as NodeListOf<HTMLElement>;
	const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new Popover(popoverTriggerEl));

	// Toggles
	(document.querySelectorAll('[data-toggle="submit"]') as NodeListOf<HTMLButtonElement>).forEach(element => {
		element.addEventListener("change", () => {
			(element.findParentElement("form") as HTMLFormElement).submit();
		});
	});
	(document.querySelectorAll('[data-toggle="scroll-to"]') as NodeListOf<HTMLElement>).forEach(element => {
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

	// Pull to load
	let _startY = 0;
	document.addEventListener('touchstart', e => {
		_startY = e.touches[0].pageY;
	}, {passive: true});

	document.addEventListener('touchend', e => {
		if (document.body.classList.contains('refreshing')) {
			window.location.reload();
		}
	});

	document.addEventListener('touchmove', e => {
		const y = e.touches[0].pageY;
		// Activate custom pull-to-refresh effects when at the top fo the container
		// and user is scrolling up.
		if (document.scrollingElement.scrollTop === 0 && y > _startY && !document.body.classList.contains('refreshing')) {
			document.body.classList.add('refreshing');
		}
	}, {passive: true});

	// Tabs activate
	(document.querySelectorAll('[role="tablist"]') as NodeListOf<HTMLDivElement>).forEach(wrap => {
		const tabs = wrap.querySelectorAll('[data-bs-toggle="tab"]') as NodeListOf<HTMLElement>;
		console.log(wrap, window.location.hash);
		tabs.forEach(tabEl => {
			const target = tabEl.dataset.bsTarget ?? tabEl.getAttribute('href');
			const tab = Tab.getOrCreateInstance(tabEl);
			console.log(target, tab, tabEl);
			if (window.location.hash === target) {
				setTimeout(() => {
					tab.show();
				}, 500);
			}
			tabEl.addEventListener('shown.bs.tab', () => {
				window.location.hash = target;
			});
		});
	});

	initCopyToClipboard();

	// Mobile nav
	const mainNav = document.getElementById('mobile-menu-full') as HTMLDivElement;
	const toggleMainNav = document.getElementById('triggerMainNav') as HTMLButtonElement;
	const closeBtn = mainNav.querySelector('.btn-close') as HTMLButtonElement;
	toggleMainNav.addEventListener('click', () => {
		mainNav.classList.toggle('show');
		toggleMainNav.classList.toggle('show');
	});
	closeBtn.addEventListener('click', () => {
		mainNav.classList.remove('show');
		toggleMainNav.classList.remove('show');
	});

	// Share buttons
	if (navigator.share) {
		(document.querySelectorAll('[data-trigger="share"]') as NodeListOf<HTMLButtonElement>).forEach(btn => {
			const title = btn.dataset.title ?? document.title;
			const text = btn.dataset.text ?? '';
			const url = btn.dataset.url ?? window.location.href;
			let shareData: ShareData = {};
			if (title !== '') {
				shareData.title = title;
			}
			if (text !== '') {
				shareData.text = text;
			}
			if (url !== '') {
				shareData.url = url;
			}

			btn.classList.remove('d-none');

			btn.addEventListener('click', async () => {
				await navigator.share(shareData);
			});
		});
	}

	// Pages
	route(page);
});