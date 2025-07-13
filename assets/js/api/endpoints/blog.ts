import { fetchPost } from "../client";

export async function uploadBlogImage(file : File, title: boolean = false, postId : number|null = null) : Promise<{data:{filePath:string, fileUrl:string}}> {
    const formData = new FormData();
    formData.append('image', file);

    if (title) {
        formData.append('title', file.name);
    }

    const url = postId > 0 ? `/blog/admin/${postId}/upload-image` : '/blog/admin/upload-image';

    return await fetchPost(url, formData);
}