import { fetchPost, SuccessResponse } from "../client";

export async function uploadBlogImage(file : File, title: boolean = false, postId : number|null = null) : Promise<{data:{filePath:string, fileUrl:string}}> {
    const formData = new FormData();
    formData.append('image', file);

    if (title) {
        formData.append('title', file.name);
    }

    const url = postId > 0 ? `/blog/admin/${postId}/upload-image` : '/blog/admin/upload-image';

    return await fetchPost(url, formData);
}

export async function approveBlogPost(postId: number): Promise<SuccessResponse<{id: number}>> {
    return await fetchPost(`/blog/admin/${postId}/approve`);
}
export async function disapproveBlogPost(postId: number): Promise<SuccessResponse<{id: number}>> {
    return await fetchPost(`/blog/admin/${postId}/disapprove`);
}