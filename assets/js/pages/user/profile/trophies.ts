import {startLoading, stopLoading} from "../../../loaders";
import {initTooltips} from "../../../functions";
import {getUserTrophies} from "../../../api/endpoints/userStats";

export default function initTrophiesTab(trophiesTabBtn: HTMLAnchorElement, trophiesTabWrapper: HTMLDivElement): void {
    let trophiesLoaded = false;

    const trophiesLoaderWrapper = document.getElementById('trophies-loader') as HTMLDivElement;
    const trophiesStatsWrapper = document.getElementById('trophies-stats') as HTMLDivElement;
    const trophiesWrapper = document.getElementById('trophies-wrapper') as HTMLDivElement;

    const trophiesAllModesCheck = document.getElementById('trophies-all-modes') as HTMLInputElement;
    const trophiesRankableModesCheck = document.getElementById('trophies-rankable-modes') as HTMLInputElement;

    const code = trophiesTabBtn.dataset.user;
    if (trophiesTabWrapper.classList.contains('show')) {
        updateTrophies();
    }
    trophiesTabBtn.addEventListener('show.bs.tab', () => {
        if (trophiesLoaded) {
            return; // Do not load data more than once
        }
        updateTrophies();
    });

    trophiesAllModesCheck.addEventListener('change', () => {
        updateTrophies();
    });
    trophiesRankableModesCheck.addEventListener('change', () => {
        updateTrophies();
    });

    function updateTrophies() {
        startLoading(true);
        getUserTrophies(code, trophiesRankableModesCheck.checked)
            .then(response => {
                stopLoading(true, true);
                trophiesLoaderWrapper.classList.add('d-none');
                trophiesStatsWrapper.classList.remove('d-none');

                trophiesWrapper.innerHTML = '';
                Object.entries(response).forEach(([key, trophy]) => {
                    const trophyEl = document.createElement('div');
                    trophyEl.classList.add('card', 'm-2', 'text-center');
                    trophyEl.style.width = '14rem';
                    trophyEl.id = 'trophy-' + key;
                    trophyEl.setAttribute('data-toggle', 'tooltip');
                    trophyEl.setAttribute('title', trophy.description);
                    trophyEl.innerHTML = `<div class="card-body">${trophy.icon}<h5 class="card-title mt-3">${trophy.name}</h5><div class="count fs-2 fw-bold">${trophy.count}&times;</div></div>`;
                    trophiesWrapper.appendChild(trophyEl);
                });
                initTooltips(trophiesWrapper);

                trophiesLoaded = true;
            })
            .catch(e => {
                console.error(e);
                stopLoading(false, true);
            });
    }
}