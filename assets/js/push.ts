import {checkPushSubscribed, pushSubscribe, pushUnsubscribe, pushUpdate} from "./api/endpoints/push";

export const vapidPublicKey: string = 'BM-ac3OzuZO9ZSPanIrFh_9CMMC8LRKudgKINPKVVkQ7eC_fBDgwfqBbFbbJBfCQSVFw_nXeeyf_V6PhaQ8s3p4';

export async function checkPush(subscription: PushSubscription) {
    try {
        const testResponse = await checkPushSubscribed(subscription.endpoint);

        if (testResponse.subscribed) {
            return;
        }

        // Resubscribe
        const response = await pushSubscribe(subscription);
        if (!response.status) {
            console.error(new Error('Invalid server response ' + JSON.stringify(response)));
        }
    } catch (e) {
        console.error(e);
    }
}

export async function updatePush(subscription: PushSubscription) {
    const response = await pushUpdate(subscription.endpoint);

    if (!response.status) {
        throw new Error('Invalid server response ' + JSON.stringify(response));
	}
}

export async function registerPush(registration: ServiceWorkerRegistration) {
	const subscription = await registration.pushManager.subscribe(
		{
			userVisibleOnly: true,
			applicationServerKey: vapidPublicKey,
		}
	);

	console.log(subscription.endpoint, (new TextDecoder()).decode(subscription.getKey('p256dh')), (new TextDecoder()).decode(subscription.getKey('auth')), subscription.toJSON());

    const response = await pushSubscribe(subscription);

    if (!response.status) {
        throw new Error('Invalid server response ' + JSON.stringify(response));
	}
	alert('Notifikace aktivní');
}

export async function unregisterPush(subscription: PushSubscription) {
	const result = await subscription.unsubscribe();
	try {
        await pushUnsubscribe(subscription.endpoint);
	} catch (e) {
		console.error(e);
	}
	if (!result) {
		throw new Error('Unsubscribe failed');
	}
	alert('Notifikace deaktivovány');
}