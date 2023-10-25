import {TeamModuleInterface} from "../teamModules";
import axios, {AxiosResponse} from "axios";
import {initTooltips} from "../../../functions";

export default class TeamModule implements TeamModuleInterface {
    init(wrapper: HTMLDivElement): void {
        initTooltips(wrapper);
    }

    async load(body: HTMLDivElement, id: number): Promise<void> {
        const response: AxiosResponse<string> = await axios.get(`/game/${gameCode}/team/${id}`);
        body.innerHTML = response.data;
        this.init(body);
        body.classList.add('loaded');
    }

}