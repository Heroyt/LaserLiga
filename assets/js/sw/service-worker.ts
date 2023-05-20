import * as navigationPreload from 'workbox-navigation-preload';
import {NetworkFirst, StaleWhileRevalidate} from 'workbox-strategies';
import {NavigationRoute, registerRoute, Route} from 'workbox-routing';
import {precacheAndRoute} from "workbox-precaching";

// Give TypeScript the correct global.
declare let self: ServiceWorkerGlobalScope;

self.addEventListener('install', () => {
	self.skipWaiting();
});

self.addEventListener('push', (event: ServiceWorkerGlobalScopeEventMap['push']) => {
	console.log(event.data, event.data.text());
	let data: {
		title: string,
		options: { body: string, image?: string, data?: string }
	} = event.data.json();
	console.log(data);
	const options: NotificationOptions = {
		body: data.options.body,
		icon: data.options.image ?? 'https://laserliga.cz/assets/favicon/android-chrome-192x192.png',
		data: data.options.data ?? '',
	}
	event.waitUntil(
		self.registration.showNotification(
			data.title,
			options
		)
	);
});

self.addEventListener('notificationclick', event => {
	event.notification.close();

	// Check if action is an url
	if (event.notification.data) {
		try {
			const url = new URL(event.notification.data);
			if (url.protocol === 'http:' || url.protocol === 'https:') {
				event.waitUntil(self.clients.openWindow(event.notification.data));
				return;
			}
		} catch (_) {
		}
	}
	event.waitUntil(self.clients.openWindow('https://laserliga.cz/'));
});

precacheAndRoute(self.__WB_MANIFEST);
navigationPreload.enable();

const navigationRoute = new NavigationRoute(new NetworkFirst({
	cacheName: 'navigations'
}));

registerRoute(navigationRoute);

const staticAssetsRoute = new Route(({request}) => {
	return ['image', 'script', 'style'].includes(request.destination);
}, new StaleWhileRevalidate({
	cacheName: 'static-assets'
}));
registerRoute(staticAssetsRoute);
