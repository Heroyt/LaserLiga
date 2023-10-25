import {Modal} from "bootstrap";
import axios, {AxiosResponse} from "axios";
import {LeaderboardModuleInterface} from "../playerModules";

export default class LeaderboardModule implements LeaderboardModuleInterface {
    private readonly modalDom: HTMLDivElement;
    private modalBody: HTMLDivElement;
    private modal: Modal;

    constructor() {
        this.modalDom = document.getElementById('leaderboard-modal') as HTMLDivElement;
        this.modalBody = this.modalDom.querySelector('.modal-body') as HTMLDivElement;
        this.modal = Modal.getOrCreateInstance(this.modalDom);
    }

    async load(url: string) {
        try {
            const response: AxiosResponse<string> = await axios.get(url);
            this.modalBody.innerHTML = response.data;
            this.modal.show();
        } catch (e) {
            console.error(e);
        }
    }

    show(): void {
        this.modal.show();
    }

    hide(): void {
        this.modal.hide();
    }
}