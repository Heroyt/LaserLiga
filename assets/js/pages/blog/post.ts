import Temml from "temml";
import { initPhotoDialog } from "../../components/photoDialog";

declare global {
    let temml: typeof Temml;
}

temml = Temml;

export default function initBlogPost() {
    console.log(temml, Temml);
    const article = document.querySelector('article');
    if (!article) {
        console.error('Blog post article not found');
        return;
    }

    console.log(temml.renderMathInElement);

    temml.renderMathInElement(article, {
        displayMode: false,
        annotate: true,
        wrap: 'tex',
    });

    // Init images
    const images = article.querySelectorAll<HTMLImageElement>('img[data-full]');
    const dialog = document.getElementById('photo-dialog') as HTMLDialogElement;
    if (images.length > 0 && dialog) {
        initPhotoDialog(images, dialog);
    }
}