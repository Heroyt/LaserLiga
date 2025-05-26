import { fetchGet, fetchPost } from "../client";

export type PushTestResponse = { status: string };

export type PushSubscribedResponse = { subscribed: boolean, id: number | null };
export type PushSubscribeResponse = { status?: string, error?: string };

export async function sendPushTestNotification(type : string = 'test'): Promise<PushTestResponse> {
    return fetchGet('/push/test', {type});
}

export async function checkPushSubscribed(endpoint: string): Promise<PushSubscribedResponse> {
    return fetchGet('/push/subscribed', {endpoint})
}

export async function pushSubscribe(subscription: PushSubscription): Promise<PushSubscribeResponse> {
    return fetchPost('/push/subscribe', subscription.toJSON());
}

export async function pushUnsubscribe(endpoint: string): Promise<PushSubscribeResponse> {
    return fetchPost('/push/unsubscribe', {endpoint});
}

export async function pushUpdate(endpoint: string): Promise<PushSubscribeResponse> {
    return fetchPost('/push/update', {endpoint});
}