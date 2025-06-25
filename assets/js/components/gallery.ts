import { initNativeDialog } from "./dialog";

const supportsWebp = document.createElement("canvas").toDataURL("image/webp").indexOf("data:image/webp") === 0;

export function initGallery(photos: NodeListOf<HTMLImageElement>, dialog: HTMLDialogElement) {
    initNativeDialog(dialog);

    const dialogImg = dialog.querySelector("img");
    const spinner = dialog.querySelector<HTMLSpanElement>(".spinner-border");
    const prevImageBtn = dialog.querySelector<HTMLButtonElement>(".prev-photo");
    const nextImageBtn = dialog.querySelector<HTMLButtonElement>(".next-photo");

    const loading = new Map<string, Promise<HTMLImageElement>>();

    let currentImage : number = null;

    for (let i = 0; i < photos.length; i++) {
        const photo = photos[i];

        // Start preloading image on hover
        photo.addEventListener('mouseenter', () => {
            preloadImage(i);
        })

        photo.addEventListener("click", () => {
            showPhoto(i);
        });
    }

    if (prevImageBtn && nextImageBtn) {
        if (photos.length < 2) {
            prevImageBtn.classList.add('d-none');
            nextImageBtn.classList.add('d-none');
        }
        else {
            prevImageBtn.addEventListener('click', prevPhoto);
            nextImageBtn.addEventListener('click', nextPhoto);
            prevImageBtn.addEventListener('mouseenter', () => {
                if (currentImage !== null) {
                    preloadImage((currentImage - 1 + photos.length) % photos.length);
                }
            });
            nextImageBtn.addEventListener('mouseenter', () => {
                if (currentImage !== null) {
                    preloadImage((currentImage + 1) % photos.length);
                }
            });
            dialog.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft') {
                    prevPhoto();
                } else if (event.key === 'ArrowRight') {
                    nextPhoto();
                } else if (event.key === 'Escape') {
                    dialog.close();
                }
            });
        }
    }

    function preloadImage(i : number) {
        if (i < 0 || i >= photos.length) {
            return;
        }
        const photo = photos[i];
        const url = supportsWebp ? photo.dataset.webp : photo.src;
        loadImage(url);
    }

    function nextPhoto() : void {
        if (currentImage === null || photos.length < 2) {
            return;
        }
        currentImage = (currentImage + 1) % photos.length;
        showPhoto(currentImage);
    }

    function prevPhoto() : void {
        if (currentImage === null || photos.length < 2) {
            return;
        }
        currentImage = (currentImage - 1 + photos.length) % photos.length;
        showPhoto(currentImage);
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
        const url = supportsWebp ? photo.dataset.webp : photo.src;

        spinner.classList.remove('d-none');
        dialogImg.src = '';
        loadImage(url)
            .then(image => {
                spinner.classList.add('d-none');
                dialogImg.src = image.src;
            })
        dialog.showModal();
        currentImage = i;
    }
}

export function initLazyPhoto(wrapper : HTMLElement|Document = document) {
    for (const photo of wrapper.querySelectorAll<HTMLElement>('.lazy-photo')) {
        const img = photo.querySelector<HTMLImageElement>('img');
        if (img.complete) {
            photo.classList.add('complete');
            return;
        }
        img.addEventListener('load', () => {
            photo.classList.add('complete');
        });
    }
}