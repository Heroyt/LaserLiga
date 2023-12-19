import {fetchPost} from "../client";

export type ApiKeyInvalidateResponse = { status: string };
export type GenerateApiKeyResponse = { key: string, id: number, name: string };

export async function generateApiKey(idArena: number): Promise<GenerateApiKeyResponse> {
    return fetchPost(`/admin/arenas${idArena}/apiKey`);
}

export async function invalidateApiKey(id: number): Promise<ApiKeyInvalidateResponse> {
    return fetchPost(`/admin/arenas/apikey/${id}/invalidate`);
}