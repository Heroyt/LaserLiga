import {fetchDelete, fetchPost, SuccessResponse} from "../client";

export type ApiKeyInvalidateResponse = { status: string };
export type GenerateApiKeyResponse = { key: string, id: number, name: string };

export async function generateApiKey(idArena: number): Promise<GenerateApiKeyResponse> {
    return fetchPost(`/admin/arenas/${idArena}/apiKey`);
}

export async function invalidateApiKey(id: number): Promise<ApiKeyInvalidateResponse> {
    return fetchPost(`/admin/arenas/apikey/${id}/invalidate`);
}

export async function assignGamePhotos(idArena: number, gameCode: string, photos: number[], secret: string|null = null): Promise<SuccessResponse<{secret:string,link:string}>> {
    return fetchPost(`/admin/arenas/${idArena}/photos/${gameCode}`, {photos, secret});
}

export async function unassignGamePhotos(idArena: number, photos: number[]): Promise<SuccessResponse> {
    return fetchPost(`/admin/arenas/${idArena}/photos/unassign`, {photos});
}

export async function setPhotosSecret(idArena: number, codes: string[], secret: string) : Promise<SuccessResponse> {
    return fetchPost(`/admin/arenas/${idArena}/photos/secret`, {codes, secret})
}

export async function setPhotosPublic(idArena: number, codes: string[], isPublic: boolean = true) : Promise<SuccessResponse> {
    return fetchPost(`/admin/arenas/${idArena}/photos/public`, {codes, 'public': isPublic})
}

export async function sendPhotosMail(idArena: number, gameCode: string, emails: string[]) : Promise<SuccessResponse> {
    return fetchPost(`/admin/arenas/${idArena}/photos/${gameCode}/mail`, {emails})
}

export async function deletePhoto(idArena: number, idPhoto: number) : Promise<SuccessResponse> {
    return fetchDelete(`/admin/arenas/${idArena}/photos/delete/${idPhoto}`);
}
export async function deletePhotos(idArena: number, ids: number[]) : Promise<SuccessResponse> {
    return fetchDelete(`/admin/arenas/${idArena}/photos`, {ids});
}