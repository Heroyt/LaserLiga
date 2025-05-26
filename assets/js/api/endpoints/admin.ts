import { fetchDelete, fetchPost, SuccessResponse } from "../client";

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

export async function sendPhotosMail(idArena: number, gameCode: string, emails: string[], message: string = '') : Promise<SuccessResponse> {
    return fetchPost(`/admin/arenas/${idArena}/photos/${gameCode}/mail`, {emails, message})
}

export async function deletePhoto(idArena: number, idPhoto: number) : Promise<SuccessResponse> {
    return fetchDelete(`/admin/arenas/${idArena}/photos/delete/${idPhoto}`);
}
export async function deletePhotos(idArena: number, ids: number[]) : Promise<SuccessResponse> {
    return fetchDelete(`/admin/arenas/${idArena}/photos`, {ids});
}

export async function uploadPhotos(idArena:number, files: FileList|File) : Promise<SuccessResponse> {
    const formData = new FormData();
    if (files instanceof File) {
        formData.append('photos[]', files);
    }
    else {
        for (let i = 0; i < files.length; i++) {
            formData.append('photos[]', files[i]);
        }
    }
    return fetchPost(`/admin/arenas/${idArena}/photos/upload`, formData)
}