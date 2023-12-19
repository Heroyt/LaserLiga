import {ArcElement, Chart, Colors, DoughnutController, Legend, Tooltip} from "chart.js";
import {startLoading, stopLoading} from "../../../loaders";
import {getUserCompare} from "../../../api/endpoints/user";

Chart.register(
    Legend,
    Tooltip,
    Colors,
    DoughnutController,
    ArcElement
);

export default function initCompareTab(compareTabBtn: HTMLAnchorElement, compareTabWrapper: HTMLDivElement): void {
    let compareLoaded = false;

    const compareLoaderWrapper = document.getElementById('compare-loader') as HTMLDivElement;
    const compareNoGamesWrapper = document.getElementById('compare-no-games') as HTMLDivElement;
    const compareStatsWrapper = document.getElementById('compare-stats') as HTMLDivElement;

    const gamesTogetherProgress = document.getElementById('games-together') as HTMLDivElement;
    const gamesEnemyProgress = document.getElementById('games-enemy') as HTMLDivElement;
    const hitsEnemyProgress = document.getElementById('hits-enemy') as HTMLDivElement;
    const hitsTogetherProgress = document.getElementById('hits-together') as HTMLDivElement;

    const winsTogether = gamesTogetherProgress.querySelector('.wins') as HTMLDivElement;
    const lossesTogether = gamesTogetherProgress.querySelector('.losses') as HTMLDivElement;
    const drawsTogether = gamesTogetherProgress.querySelector('.draws') as HTMLDivElement;

    const winsEnemy = gamesEnemyProgress.querySelector('.wins') as HTMLDivElement;
    const lossesEnemy = gamesEnemyProgress.querySelector('.losses') as HTMLDivElement;
    const drawsEnemy = gamesEnemyProgress.querySelector('.draws') as HTMLDivElement;

    const hitsEnemy = hitsEnemyProgress.querySelector('.hits') as HTMLDivElement;
    const deathsEnemy = hitsEnemyProgress.querySelector('.deaths') as HTMLDivElement;

    const hitsTogether = hitsTogetherProgress.querySelector('.hits') as HTMLDivElement;
    const deathsTogether = hitsTogetherProgress.querySelector('.deaths') as HTMLDivElement;

    const gamesCompareCanvas = document.getElementById('games-compare-graph') as HTMLCanvasElement;

    const gamesCompareChart = new Chart(gamesCompareCanvas, {
        type: "doughnut", data: {
            labels: [gamesCompareCanvas.dataset.labelTogether, gamesCompareCanvas.dataset.labelEnemyTeam, gamesCompareCanvas.dataset.labelEnemySolo,],
            datasets: [{
                data: [],
                backgroundColor: ['rgb(54, 162, 235)', 'rgb(255, 99, 132)', 'rgb(255, 205, 86)',],
                borderWidth: 0,
            }],
        }, options: {
            maintainAspectRatio: false, plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
    const totalGamesTogether = document.getElementById('total-games-together') as HTMLSpanElement;

    const code = compareTabBtn.dataset.user;
    if (compareTabWrapper.classList.contains('show')) {
        loadCompare();
    }
    compareTabBtn.addEventListener('show.bs.tab', () => {
        if (compareLoaded) {
            return; // Do not load data more than once
        }
        loadCompare();
    });

    function loadCompare() {
        startLoading(true);
        getUserCompare(code)
            .then(response => {
                stopLoading(true, true);
                compareLoaderWrapper.classList.add('d-none');
                if (response.gameCount <= 0) {
                    compareNoGamesWrapper.classList.remove('d-none');
                    return;
                }
                compareStatsWrapper.classList.remove('d-none');

                if (response.gameCountTogether === 0) {
                    compareStatsWrapper.querySelectorAll('.compare-stat-together').forEach(el => {
                        el.classList.add('d-none');
                    });
                } else {
                    (winsTogether.querySelector('span') as HTMLSpanElement).innerText = response.winsTogether.toString();
                    (lossesTogether.querySelector('span') as HTMLSpanElement).innerText = response.lossesTogether.toString();
                    (drawsTogether.querySelector('span') as HTMLSpanElement).innerText = response.drawsTogether.toString();

                    (hitsTogether.querySelector('span') as HTMLSpanElement).innerText = response.hitsTogether.toString();
                    (deathsTogether.querySelector('span') as HTMLSpanElement).innerText = response.deathsTogether.toString();

                    winsTogether.style.width = `${100 * response.winsTogether / response.gameCountTogether}%`;
                    winsTogether.setAttribute('aria-valuenow', `${100 * response.winsTogether / response.gameCountTogether}`);
                    lossesTogether.style.width = `${100 * response.lossesTogether / response.gameCountTogether}%`;
                    lossesTogether.setAttribute('aria-valuenow', `${100 * response.lossesTogether / response.gameCountTogether}`);
                    drawsTogether.style.width = `${100 * response.drawsTogether / response.gameCountTogether}%`;
                    drawsTogether.setAttribute('aria-valuenow', `${100 * response.drawsTogether / response.gameCountTogether}`);

                    const hitsTogetherTotal = response.hitsTogether + response.deathsTogether;

                    hitsTogether.style.width = hitsTogetherTotal === 0 ? '50%' : `${100 * response.hitsTogether / hitsTogetherTotal}%`;
                    hitsTogether.setAttribute('aria-valuenow', `${100 * response.hitsTogether / hitsTogetherTotal}`);
                    deathsTogether.style.width = hitsTogetherTotal === 0 ? '50%' : `${100 * response.deathsTogether / hitsTogetherTotal}%`;
                    deathsTogether.setAttribute('aria-valuenow', `${100 * response.deathsTogether / hitsTogetherTotal}`);
                }

                if (response.gameCountEnemy === 0) {
                    compareStatsWrapper.querySelectorAll('.compare-stat-enemy').forEach(el => {
                        el.classList.add('d-none');
                    });
                } else {
                    (winsEnemy.querySelector('span') as HTMLSpanElement).innerText = response.winsEnemy.toString();
                    (lossesEnemy.querySelector('span') as HTMLSpanElement).innerText = response.lossesEnemy.toString();
                    (drawsEnemy.querySelector('span') as HTMLSpanElement).innerText = response.drawsEnemy.toString();

                    (hitsEnemy.querySelector('span') as HTMLSpanElement).innerText = response.hitsEnemy.toString();
                    (deathsEnemy.querySelector('span') as HTMLSpanElement).innerText = response.deathsEnemy.toString();

                    winsEnemy.style.width = `${100 * response.winsEnemy / response.gameCountEnemy}%`;
                    winsEnemy.setAttribute('aria-valuenow', `${100 * response.winsEnemy / response.gameCountEnemy}`);
                    lossesEnemy.style.width = `${100 * response.lossesEnemy / response.gameCountEnemy}%`;
                    lossesEnemy.setAttribute('aria-valuenow', `${100 * response.lossesEnemy / response.gameCountEnemy}`);
                    drawsEnemy.style.width = `${100 * response.drawsEnemy / response.gameCountEnemy}%`;
                    drawsEnemy.setAttribute('aria-valuenow', `${100 * response.drawsEnemy / response.gameCountEnemy}`);

                    const hitsEnemyTotal = response.hitsEnemy + response.deathsEnemy;

                    hitsEnemy.style.width = hitsEnemyTotal === 0 ? '50%' : `${100 * response.hitsEnemy / hitsEnemyTotal}%`;
                    hitsEnemy.setAttribute('aria-valuenow', `${100 * response.hitsEnemy / hitsEnemyTotal}`);
                    deathsEnemy.style.width = hitsEnemyTotal === 0 ? '50%' : `${100 * response.deathsEnemy / hitsEnemyTotal}%`;
                    deathsEnemy.setAttribute('aria-valuenow', `${100 * response.deathsEnemy / hitsEnemyTotal}`);
                }

                totalGamesTogether.innerText = response.gameCount.toString();
                gamesCompareChart.data.datasets[0].data[0] = response.gameCountTogether;
                gamesCompareChart.data.datasets[0].data[1] = response.gameCountEnemyTeam;
                gamesCompareChart.data.datasets[0].data[2] = response.gameCountEnemySolo;
                gamesCompareChart.update();

                compareLoaded = true;
            })
            .catch(e => {
                console.error(e);
                stopLoading(false, true);
            })
    }
}