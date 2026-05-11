import { fetchPost, SuccessResponse } from "../../api/client";
import { startLoading, stopLoading } from "../../loaders";
import "codemirror/addon/mode/simple";
import "codemirror/addon/hint/show-hint.js";
import "codemirror/addon/hint/anyword-hint.js";
import EasyMDE from "easymde";
import { approveBlogPost, disapproveBlogPost, uploadBlogImage } from "../../api/endpoints/blog";
import { EmojiToken, markedEmoji } from "marked-emoji";
import { emojis } from "./emojis";
import { triggerNotificationError } from "../../components/notifications";
import CodeMirror, { Editor, EditorChange } from "codemirror";

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
    const emojiExtension : {extensions: Array<any>} = markedEmoji({
        emojis,
        renderer: (token : EmojiToken<string>) => token.emoji,
    });

    CodeMirror.defineSimpleMode('markdown-extension', {
        start: [
            {regex: /:\w+:/, token: 'emoji'},
            {regex: /\$\$.*?\$\$/, token: 'math'},
        ],
    });

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
            {
              name: 'math',
                action: (editor) => {
                    let text = editor.codemirror.getSelection();
                    text.split('$$').join('');
                    text = text.split('__').join('');
                    const endPoint = editor.codemirror.getCursor('end');
                    editor.codemirror.replaceSelection('$$' + text + '$$');

                    endPoint.ch += 2;
                    editor.codemirror.setCursor(endPoint);
                    editor.codemirror.focus();
                },
                className: 'fa-solid fa-calculator',
                title: 'Insert Math (LaTeX)',
            },
            '|',
            'preview', 'side-by-side', 'fullscreen'
        ],
        uploadImage: true,
        imageUploadEndpoint: postId > 0 ? `/blog/admin/${postId}/upload-image` : '/blog/admin/upload-image',
        renderingConfig: {
            markedOptions: {
                gfm: true,
                extensions: [
                    ...emojiExtension.extensions,
                ]
            }
        },
        overlayMode: {
            combine: true,
            mode: CodeMirror.getMode({}, 'markdown-extension'),
        }
    });

    const getWordAtCursor = (cm : Editor) : string => {
        const cursor = cm.getCursor();
        const range = cm.findWordAt(cursor);
        const from = range.from();
        from.ch--; // Adjust to include the colon in the range
        return cm.getRange(from, range.to()).trim();
    }

    const hint = (cm : Editor, options) => {
        const cursor = cm.getCursor();
        const token = getWordAtCursor(cm);
        const re = /:(\w*)/;
        if (!re.test(token)) return;

        const completions = Object.entries(emojis)
            .filter(([name, emoji]) => {
                return name.startsWith(token.slice(1));
            })
            .map(([name, emoji]) : Completion => {
                return {
                    text: `:${name}:`.replace(token, ''),
                    displayText: `:${name}: ${emoji}`,
                }
            });

        return {
            list: completions,
            from: CodeMirror.Pos(cursor.line, token.start),
            to: CodeMirror.Pos(cursor.line, token.end),
        }
    };
    editor.codemirror.setOption(
        'hintOptions',
        {
            hint,
        }
    );
    editor.codemirror.refresh();

    editor.codemirror.on('change', (cm : CodeMirror, changeObj : EditorChange) => {
        const token = getWordAtCursor(cm);
        const re = /:\w+/;
        if (re.test(token)) {
            cm.showHint({
                hint,
            });
        }
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

    const approveButton = document.getElementById('approve-post') as HTMLButtonElement|null;
    if (approveButton) {
        approveButton.addEventListener('click', async (event) => {
            event.preventDefault();

            startLoading();
            try {
                const response = await approveBlogPost(postId);
                stopLoading(true);
                if (response.values.id) {
                    window.location.reload();
                }
            } catch (e) {
                stopLoading(false);
                await triggerNotificationError(e);
            }
        });
    }

    const disapproveButton = document.getElementById('disapprove-post') as HTMLButtonElement|null;
    if (disapproveButton) {
        disapproveButton.addEventListener('click', async (event) => {
            event.preventDefault();

            startLoading();
            try {
                const response = await disapproveBlogPost(postId);
                stopLoading(true);
                if (response.values.id) {
                    window.location.reload();
                }
            } catch (e) {
                stopLoading(false);
                await triggerNotificationError(e);
            }
        });
    }
}