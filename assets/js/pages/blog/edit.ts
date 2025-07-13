import { fetchPost, SuccessResponse } from "../../api/client";
import { startLoading, stopLoading } from "../../loaders";
import EasyMDE from "easymde";
import { uploadBlogImage } from "../../api/endpoints/blog";

export default function initBlogPostEditPage() : void {
    const form = document.querySelector<HTMLFormElement>('#blog-post-form');
    if (!form) {
        console.error('Blog post edit form not found');
        return;
    }

    const isCreate = 'create' in form.dataset;
    const postId = parseInt(form.dataset.edit ?? '0');

    const imageUploadInput = form.querySelector<HTMLInputElement>('#image-upload');
    const imageUploadUrl = form.querySelector<HTMLInputElement>('#image-upload-url');
    const imageUploadPreview = form.querySelector<HTMLImageElement>('#image-upload-preview');

    const content = form.querySelector<HTMLTextAreaElement>('#post-content');
    const editor = new EasyMDE({
        element: content,
        spellChecker: false,
        autoDownloadFontAwesome: false,
        autosave: {
            enabled: true,
            uniqueId: content.dataset.uniqueId ?? 'blog-post',
            delay: 1000
        },
        toolbar: [
            'bold', 'italic', 'heading', '|',
            'quote', 'unordered-list', 'ordered-list', '|',
            'link', 'image', '|',
            'preview', 'side-by-side', 'fullscreen'
        ],
        uploadImage: true,
        imageUploadEndpoint: postId > 0 ? `/blog/admin/${postId}/upload-image` : '/blog/admin/upload-image',
    });

    if (imageUploadInput && imageUploadUrl) {
        imageUploadInput.addEventListener('change', async (event) => {
            const file = (event.target as HTMLInputElement).files?.[0];
            if (!file) return;

            startLoading();
            try {
                const response = await uploadBlogImage(file, true, postId > 0 ? postId : null);
                imageUploadUrl.value = response.data.fileUrl;
                imageUploadInput.files = null; // Clear the input after upload
                if (imageUploadPreview) {
                    imageUploadPreview.src = response.data.fileUrl;
                }
            } catch (e) {
                console.error('Image upload failed:', e);
            } finally {
                stopLoading();
            }
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        formData.set('content', editor.value());
        startLoading();
        try {
            const response : SuccessResponse<{id?:number}> = await fetchPost(window.location.href, formData);
            stopLoading(true);
            if (response.values.id && isCreate) {
                window.location.href = `/blog/admin/${response.values.id}`;
            }
        } catch (e) {
            stopLoading(false);
        }
    });

    // Additional initialization logic can go here
    console.log('Blog post edit page initialized');
}