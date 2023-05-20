import {registerPush, unregisterPush} from "../../push";
import axios from "axios";
import {startLoading, stopLoading} from "../../loaders";

export default function initUserSettings() {
	if ('serviceWorker' in navigator && 'PushManager' in window) {
		const section = document.getElementById('notification-settings') as HTMLDivElement;

		section.classList.remove('d-none');

		const registerBtn = document.getElementById('registerSubscription') as HTMLButtonElement;
		const unregisterBtn = document.getElementById('unregisterSubscription') as HTMLButtonElement;
		const testBtn = document.getElementById('testNotification') as HTMLButtonElement;

		navigator.serviceWorker.getRegistration()
			.then(async registration => {
				if (!registration) {
					throw new Error('No service worker registered')
				}
				await updateButtons(registration);

				registerBtn.addEventListener('click', async () => {
					const result = await Notification.requestPermission()
					await updateButtons(registration);
					if (result === 'denied') {
						console.error('The user explicitly denied the permission request.');
						return;
					}
					if (result === 'granted') {
						console.info('The user accepted the permission request.');
					}
					const subscribed = await registration.pushManager.getSubscription();
					if (!subscribed) {
						await registerPush(registration);
					}
				});

				unregisterBtn.addEventListener('click', async () => {
					const subscription = await registration.pushManager.getSubscription();
					console.log(subscription);
					if (subscription) {
						await unregisterPush(subscription);
					} else {
						await registerPush(registration);
					}
					await updateButtons(registration);
				});
				testBtn.addEventListener('click', () => {
					startLoading();
					axios.get('/push/test')
						.then(() => {
							stopLoading(true);
						})
						.catch(() => {
							stopLoading(false);
						})

				});
			});

		async function updateButtons(registration: ServiceWorkerRegistration) {
			const subscription = await registration.pushManager.getSubscription();
			console.log(subscription, Notification.permission);
			if (!subscription) {
				unregisterBtn.classList.add('d-none');
				testBtn.classList.add('d-none');
				registerBtn.classList.remove('d-none');
			} else {
				registerBtn.classList.add('d-none');
				unregisterBtn.classList.remove('d-none');
				testBtn.classList.remove('d-none');
			}
		}
	} else {
		alert('Zařízení nepodporuje push notifikace');
	}
}