import { initNativeDialog } from "./dialog";

const supportsWebp = document.createElement("canvas").toDataURL("image/webp").indexOf("data:image/webp") === 0;

export function initPhotoDialog(photos: NodeListOf<HTMLImageElement>, dialog: HTMLDialogElement) {
    initNativeDialog(dialog);

    const dialogImg = dialog.querySelector("img");
    const spinner = dialog.querySelector<HTMLSpanElement>(".spinner-border");

    const loading = new Map<string, Promise<HTMLImageElement>>();

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            dialog.close();
        }
    });

    for (let i = 0; i < photos.length; i++) {
        const photo = photos[i];

        photo.style.cursor = "zoom-in";

        // Start preloading image on hover
        photo.addEventListener('mouseenter', async () => {
            await preloadImage(i);
        })

        photo.addEventListener("click", () => {
            showPhoto(i);
        });
    }

    async function preloadImage(i : number) {
        if (i < 0 || i >= photos.length) {
            return;
        }
        const photo = photos[i];
        const url = supportsWebp ? (photo.dataset.webp ?? photo.src) : (photo.dataset.full ?? photo.src);
        await loadImage(url);
    }

    function loadImage(url: string): Promise<HTMLImageElement> {
        if (loading.has(url)) {
            return loading.get(url)!;
        }

        const img = new Image();
        img.src = url;
        const promise = new Promise<HTMLImageElement>((resolve, reject) => {
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error(`Failed to load image: ${url}`));
        });

        loading.set(url, promise);
        return promise;
    }

    function showPhoto(i: number) {
        if (i < 0 || i >= photos.length) {
            return;
        }
        const photo = photos[i];
        const url = supportsWebp ? (photo.dataset.webp ?? photo.src) : (photo.dataset.full ?? photo.src);

        spinner.classList.remove('d-none');
        dialogImg.src = '';
        loadImage(url)
            .then(image => {
                spinner.classList.add('d-none');
                dialogImg.src = image.src;
            })
        dialog.showModal();
    }
}