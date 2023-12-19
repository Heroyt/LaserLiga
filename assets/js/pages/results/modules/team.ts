import {TeamModuleInterface} from "../teamModules";
import {initTooltips} from "../../../functions";
import {getGameTeamResults} from "../../../api/endpoints/game";

export default class TeamModule implements TeamModuleInterface {
    init(wrapper: HTMLDivElement): void {
        initTooltips(wrapper);
    }

    async load(body: HTMLDivElement, id: number): Promise<void> {
        body.innerHTML = await getGameTeamResults(gameCode, id);
        this.init(body);
        body.classList.add('loaded');
    }

}