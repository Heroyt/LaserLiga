import {initSetMe} from "../../../userPlayer";
import {initTooltips} from "../../../functions";
import {
    DistributionModuleInterface,
    EloModuleInterface,
    LeaderboardModuleInterface,
    PlayerModuleInterface
} from "../playerModules";
import {getGamePlayerResults} from "../../../api/endpoints/game";

export default class PlayerModule implements PlayerModuleInterface {

    private distributionModule: DistributionModuleInterface;
    private leaderboardModule: LeaderboardModuleInterface;
    private eloModule: EloModuleInterface;

    private async getDistribution(): Promise<DistributionModuleInterface> {
        if (this.distributionModule) {
            return this.distributionModule;
        }
        const module = await import(/* webpackChunkName: "modules/playerDistributionModule" */ './playerDistribution');
        this.distributionModule = new module.default;
        return this.distributionModule;
    }

    private async getLeaderboard(): Promise<LeaderboardModuleInterface> {
        if (this.leaderboardModule) {
            return this.leaderboardModule;
        }
        const module = await import(/* webpackChunkName: "modules/playerLeaderboardModule" */ './playerLeaderboard');
        this.leaderboardModule = new module.default;
        return this.leaderboardModule;
    }

    private async getElo(): Promise<EloModuleInterface> {
        if (this.eloModule) {
            return this.eloModule;
        }
        const module = await import(/* webpackChunkName: "modules/playerEloModule" */ './playerElo');
        this.eloModule = new module.default;
        return this.eloModule;
    }

    async load(body: HTMLDivElement, id: number) {
        body.innerHTML = await getGamePlayerResults(gameCode, id);
        await this.init(body);
        body.classList.add('loaded');
    }

    async init(wrapper: HTMLDivElement) {
        initSetMe(wrapper);
        initTooltips(wrapper);

        (wrapper.querySelectorAll('.show-leaderboard') as NodeListOf<HTMLButtonElement>).forEach(btn => {
            const url = btn.dataset.href;
            btn.addEventListener('click', async () => {
                _paq.push(['trackEvent', 'Results', 'ShowLeaderboard', btn.dataset.category, btn.dataset.player]);
                const leaderboard = (await this.getLeaderboard());
                await leaderboard.load(url);
            });
        });

        (wrapper.querySelectorAll('.show-distribution') as NodeListOf<HTMLButtonElement>).forEach(btn => {
            const playerId = parseInt(btn.dataset.id);
            const playerName = btn.dataset.name;
            const param = btn.dataset.param;
            const paramName = btn.dataset.paramName;
            btn.addEventListener('click', async () => {
                const distribution = await this.getDistribution();
                distribution.setTitle(`${playerName} - ${paramName}`)
                distribution.selectedPlayer = playerId;
                distribution.selectedParam = param;
                await distribution.load();
                distribution.show();
            });
        });

        const showEloBtn = wrapper.querySelector('.show-elo-info') as HTMLButtonElement | undefined;
        if (showEloBtn) {
            const code = showEloBtn.dataset.code;
            const id = showEloBtn.dataset.id;
            showEloBtn.addEventListener('click', async () => {
                _paq.push(['trackEvent', 'Results', 'ShowElo', showEloBtn.dataset.player]);
                const eloModule = await this.getElo();
                await eloModule.load(`/game/${code}/player/${id}/elo`);
            });
        }
    }

}