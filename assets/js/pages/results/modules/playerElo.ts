import {EloModuleInterface} from "../playerModules";
import {Modal} from "bootstrap";
import axios, {AxiosResponse} from "axios";
import {startLoading, stopLoading} from "../../../loaders";
import {initPopovers} from "../../../functions";

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
            const response: AxiosResponse<string> = await axios.get(url);
            this.modalBody.innerHTML = response.data;
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