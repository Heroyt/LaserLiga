import { Modal } from "bootstrap";
import { PlayerModuleInterface } from "./playerModules";
import { TeamModuleInterface } from "./teamModules";
import { getGameHighlights, makePhotosHidden, makePhotosPublic } from "../../api/endpoints/game";
import { startLoading, stopLoading } from "../../loaders";
import { triggerNotificationError } from "../../components/notifications";
import { initGallery } from "../../components/gallery";

declare global {
    const gameCode: string;
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
    if (modalDom) {
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

    initPhotos();
}

async function loadHighlights() {
    try {
        const response = await getGameHighlights(gameCode);
        const highlightsWrapper = document.querySelector('.results-highlights') as HTMLDivElement;
        const top = highlightsWrapper.querySelector('.top') as HTMLDivElement;
        const empty = highlightsWrapper.querySelector('.empty') as HTMLDivElement;
        const collapse = highlightsWrapper.querySelector('#highlights-collapse') as HTMLDivElement;
        const more = highlightsWrapper.querySelector('.show-more') as HTMLParagraphElement;

        let count = 0;
        for (const highlight of response) {
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
            if (count > 20) {
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

function initPhotos() {
    const photos = document.querySelectorAll<HTMLImageElement>('.game-photo img');
    const dialog = document.getElementById('photo-dialog') as HTMLDialogElement;
    if (photos.length === 0 || !dialog) {
        console.log('Skip game photos')
        return;
    }

    initGallery(photos, dialog);

    const makePublic = document.getElementById('make-photos-public') as HTMLButtonElement;
    if (makePublic) {
        makePublic.addEventListener('click', () => {
            if (!confirm(makePublic.dataset.confirm)) {
                return;
            }
            startLoading(true);
            makePhotosPublic(gameCode)
                .then(() => {
                    window.location.reload();
                })
                .catch(async (e) => {
                    stopLoading(false, true);
                    await triggerNotificationError(e);
                });
        });
    }
    const makeHidden = document.getElementById('make-photos-hidden') as HTMLButtonElement;
    if (makeHidden) {
        makeHidden.addEventListener('click', () => {
            if (!confirm(makeHidden.dataset.confirm)) {
                return;
            }
            startLoading(true);
            makePhotosHidden(gameCode)
                .then(() => {
                    window.location.reload();
                })
                .catch(async (e) => {
                    stopLoading(false, true);
                    await triggerNotificationError(e);
                });
        });
    }
}