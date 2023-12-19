import {EloModuleInterface} from "../playerModules";
import {Modal} from "bootstrap";
import {startLoading, stopLoading} from "../../../loaders";
import {initPopovers} from "../../../functions";
import {fetchGet} from "../../../api/client";

export default class EloModule implements EloModuleInterface {

    private readonly modalDom: HTMLDivElement;
    private readonly modalBody: HTMLDivElement;
    private modal: Modal;

    constructor() {
        this.modalDom = document.getElementById('elo-modal') as HTMLDivElement | null;
        this.modalBody = this.modalDom.querySelector('.modal-body') as HTMLDivElement;
        this.modal = Modal.getOrCreateInstance(this.modalDom);
    }

    async load(url: string): Promise<void> {
        this.modalBody.innerHTML = '';
        startLoading();
        try {
            this.modalBody.innerHTML = await fetchGet(url);
            initPopovers(this.modalBody);
            stopLoading(true);
            this.show();
        } catch (e) {
            console.error(e);
            stopLoading(false);
        }
    }

    show(): void {
        this.modal.show();
    }

    hide(): void {
        this.modal.hide();
    }

}