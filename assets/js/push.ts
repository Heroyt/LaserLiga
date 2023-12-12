import axios, {AxiosResponse} from "axios";

export const vapidPublicKey: string = 'BM-ac3OzuZO9ZSPanIrFh_9CMMC8LRKudgKINPKVVkQ7eC_fBDgwfqBbFbbJBfCQSVFw_nXeeyf_V6PhaQ8s3p4';

export async function checkPush(subscription: PushSubscription) {
    try {
        const params = new URLSearchParams;
        params.set('endpoint', subscription.endpoint);
        const testResponse: AxiosResponse<{ subscribed: boolean, id: number | null }> = await axios.get(
            '/push/subscribed?' + params.toString()
        );

        if (testResponse.data.subscribed) {
            return;
        }

        // Resubscribe
        const response: AxiosResponse<{ status?: string, error?: string }> = await axios.post(
            '/push/subscribe',
            subscription.toJSON(),
        );

        if (response.status !== 200) {
            console.error(new Error('Invalid server response ' + JSON.stringify(response.data)));
        }
    } catch (e) {
        console.error(e);
    }
}

export async function updatePush(subscription: PushSubscription) {
	const response: AxiosResponse<{ status?: string, error?: string }> = await axios.post(
		'/push/update',
		{
			endpoint: subscription.endpoint,
		}
	);

	if (response.status !== 200) {
		throw new Error('Invalid server response ' + JSON.stringify(response.data));
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

	const response: AxiosResponse<{ status?: string, error?: string }> = await axios.post(
		'/push/subscribe',
		subscription.toJSON(),
	);

	if (response.status !== 200) {
		throw new Error('Invalid server response ' + JSON.stringify(response.data));
	}
	alert('Notifikace aktivní');
}

export async function unregisterPush(subscription: PushSubscription) {
	const result = await subscription.unsubscribe();
	try {
		await axios.post(
			'/push/unsubscribe',
			{
				endpoint: subscription.endpoint,
			}
		);
	} catch (e) {
		console.error(e);
	}
	if (!result) {
		throw new Error('Unsubscribe failed');
	}
	alert('Notifikace deaktivovány');
}