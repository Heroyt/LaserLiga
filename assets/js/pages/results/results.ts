import {Modal} from 'bootstrap';
import axios, {AxiosResponse} from "axios";
import {PlayerModuleInterface} from "./playerModules";
import {TeamModuleInterface} from "./teamModules";

declare global {
    const gameCode: string;
}

interface DistributionResponse {
    player: { [index: string]: any },
    distribution: { [index: string]: number },
    percentile: number,
    min: number,
    max: number,
    value: number,
}

interface HighlightResponse {
    rarity: number,
    description: string,
    html: string
}

export default function initResults() {
    const container = document.querySelector('.game-results') as HTMLDivElement;
    const modeSelect = document.querySelectorAll(`input[name="result-mode-select"]`) as NodeListOf<HTMLInputElement>;
    const teamsBody = document.querySelectorAll('.team-stats') as NodeListOf<HTMLDivElement>;
    modeSelect.forEach(input => {
        input.addEventListener('change', () => {
            let value = '';
            modeSelect.forEach(i => {
                if (i.checked) {
                    value = i.value;
                }
            });
            _paq.push(['trackEvent', 'Results', 'ModeSwitch', value]);
            if (value === 'teams') {
                container.classList.add('teams-active');
                teamsBody.forEach(async (teamBody) => {
                    if (teamBody.classList.contains('loaded')) {
                        return;
                    }
                    const teamId = parseInt(teamBody.dataset.id);
                    // noinspection JSIgnoredPromiseFromCall
                    const teamModule = await getTeamModule();
                    await teamModule.load(teamBody, teamId);
                });
            } else {
                container.classList.remove('teams-active');
            }
        });
    });

    // Auto-open tournament modal
    const modalDom = document.getElementById('tournament-modal') as HTMLDivElement;
    const dontShow = modalDom.querySelector('#dont-show') as HTMLButtonElement;
    const modal = new Modal(modalDom);
    const hide = window.localStorage.getItem('hide-tournament-modal') === 'true';
    if (modalDom.dataset.show && modalDom.dataset.show === 'true' && !hide) {
        modal.show();
        dontShow.addEventListener('click', () => {
            window.localStorage.setItem('hide-tournament-modal', 'true');
            modal.hide();
        });
    }

    (document.querySelectorAll('.player-body') as NodeListOf<HTMLDivElement>).forEach(playerBody => {
        playerBody.addEventListener('show.bs.collapse', async () => {
            if (playerBody.classList.contains('loaded')) {
                return;
            }
            const playerModule = await getPlayerModule();
            const playerId = parseInt(playerBody.dataset.id);
            await playerModule.load(playerBody, playerId);
        });
    });

    loadHighlights();

    let playerModule: PlayerModuleInterface;

    async function getPlayerModule(): Promise<PlayerModuleInterface> {
        if (playerModule) {
            return playerModule;
        }
        const module = await import(/* webpackChunkName: "modules/playerModule" */ './modules/player');
        playerModule = new module.default;
        return playerModule;
    }

    let teamModule: TeamModuleInterface;

    async function getTeamModule(): Promise<TeamModuleInterface> {
        if (teamModule) {
            return teamModule;
        }
        const module = await import(/* webpackChunkName: "modules/teamModule" */ './modules/team');
        teamModule = new module.default;
        return teamModule;
    }

}

async function loadHighlights() {
    try {
        const response: AxiosResponse<HighlightResponse[]> = await axios.get(`/game/${gameCode}/highlights`);
        const highlightsWrapper = document.querySelector('.results-highlights') as HTMLDivElement;
        const top = highlightsWrapper.querySelector('.top') as HTMLDivElement;
        const empty = highlightsWrapper.querySelector('.empty') as HTMLDivElement;
        const collapse = highlightsWrapper.querySelector('#highlights-collapse') as HTMLDivElement;
        const more = highlightsWrapper.querySelector('.show-more') as HTMLParagraphElement;

        let count = 0;
        for (const highlight of response.data) {
            const wrapper = document.createElement('div');
            wrapper.classList.add('highlight');
            wrapper.setAttribute('data-rarity', highlight.rarity.toString());
            wrapper.innerHTML = highlight.html;
            if (count < 3) {
                top.appendChild(wrapper);
            } else {
                collapse.appendChild(wrapper);
            }
            count++;
            if (count > 10) {
                break;
            }

            wrapper.addEventListener('hover', () => {
                _paq.push(['trackEvent', 'Highlights', 'Hover', gameCode, count.toString()]);
            });

            wrapper.addEventListener('click', () => {
                _paq.push(['trackEvent', 'Highlights', 'Click', gameCode, count.toString()]);
            });

            wrapper.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    _paq.push(['trackEvent', 'Highlights', 'ClickPlayer', gameCode, link.dataset.name]);
                });
            });
        }
        if (count > 0) {
            empty.remove();
        }
        if (count > 3) {
            more.classList.remove('d-none');

            highlightsWrapper.querySelector('button').addEventListener('click', () => {
                _paq.push(['trackEvent', 'Highlights', 'ShowMore', gameCode]);
            });
        }
    } catch (e) {
        console.error(e);
    }
}