import {Chart} from "chart.js/auto";
import {startLoading, stopLoading} from "../../../loaders";
import axios, {AxiosResponse} from "axios";

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
    compareTabBtn.addEventListener('show.bs.tab', e => {
        if (compareLoaded) {
            return; // Do not load data more than once
        }
        loadCompare();
    });

    function loadCompare() {
        startLoading(true);
        axios.get('/user/' + code + '/compare')
            .then((response: AxiosResponse<{
                gameCount: number,
                gameCountTogether: number,
                gameCountEnemy: number,
                gameCountEnemyTeam: number,
                gameCountEnemySolo: number,
                winsTogether: number,
                lossesTogether: number,
                drawsTogether: number,
                winsEnemy: number,
                lossesEnemy: number,
                drawsEnemy: number,
                hitsEnemy: number,
                deathsEnemy: number,
                hitsTogether: number,
                deathsTogether: number
            }>) => {
                stopLoading(true, true);
                compareLoaderWrapper.classList.add('d-none');
                if (response.data.gameCount <= 0) {
                    compareNoGamesWrapper.classList.remove('d-none');
                    return;
                }
                compareStatsWrapper.classList.remove('d-none');

                if (response.data.gameCountTogether === 0) {
                    compareStatsWrapper.querySelectorAll('.compare-stat-together').forEach(el => {
                        el.classList.add('d-none');
                    });
                } else {
                    (winsTogether.querySelector('span') as HTMLSpanElement).innerText = response.data.winsTogether.toString();
                    (lossesTogether.querySelector('span') as HTMLSpanElement).innerText = response.data.lossesTogether.toString();
                    (drawsTogether.querySelector('span') as HTMLSpanElement).innerText = response.data.drawsTogether.toString();

                    (hitsTogether.querySelector('span') as HTMLSpanElement).innerText = response.data.hitsTogether.toString();
                    (deathsTogether.querySelector('span') as HTMLSpanElement).innerText = response.data.deathsTogether.toString();

                    winsTogether.style.width = `${100 * response.data.winsTogether / response.data.gameCountTogether}%`;
                    winsTogether.setAttribute('aria-valuenow', `${100 * response.data.winsTogether / response.data.gameCountTogether}`);
                    lossesTogether.style.width = `${100 * response.data.lossesTogether / response.data.gameCountTogether}%`;
                    lossesTogether.setAttribute('aria-valuenow', `${100 * response.data.lossesTogether / response.data.gameCountTogether}`);
                    drawsTogether.style.width = `${100 * response.data.drawsTogether / response.data.gameCountTogether}%`;
                    drawsTogether.setAttribute('aria-valuenow', `${100 * response.data.drawsTogether / response.data.gameCountTogether}`);

                    const hitsTogetherTotal = response.data.hitsTogether + response.data.deathsTogether;

                    hitsTogether.style.width = hitsTogetherTotal === 0 ? '50%' : `${100 * response.data.hitsTogether / hitsTogetherTotal}%`;
                    hitsTogether.setAttribute('aria-valuenow', `${100 * response.data.hitsTogether / hitsTogetherTotal}`);
                    deathsTogether.style.width = hitsTogetherTotal === 0 ? '50%' : `${100 * response.data.deathsTogether / hitsTogetherTotal}%`;
                    deathsTogether.setAttribute('aria-valuenow', `${100 * response.data.deathsTogether / hitsTogetherTotal}`);
                }

                if (response.data.gameCountEnemy === 0) {
                    compareStatsWrapper.querySelectorAll('.compare-stat-enemy').forEach(el => {
                        el.classList.add('d-none');
                    });
                } else {
                    (winsEnemy.querySelector('span') as HTMLSpanElement).innerText = response.data.winsEnemy.toString();
                    (lossesEnemy.querySelector('span') as HTMLSpanElement).innerText = response.data.lossesEnemy.toString();
                    (drawsEnemy.querySelector('span') as HTMLSpanElement).innerText = response.data.drawsEnemy.toString();

                    (hitsEnemy.querySelector('span') as HTMLSpanElement).innerText = response.data.hitsEnemy.toString();
                    (deathsEnemy.querySelector('span') as HTMLSpanElement).innerText = response.data.deathsEnemy.toString();

                    winsEnemy.style.width = `${100 * response.data.winsEnemy / response.data.gameCountEnemy}%`;
                    winsEnemy.setAttribute('aria-valuenow', `${100 * response.data.winsEnemy / response.data.gameCountEnemy}`);
                    lossesEnemy.style.width = `${100 * response.data.lossesEnemy / response.data.gameCountEnemy}%`;
                    lossesEnemy.setAttribute('aria-valuenow', `${100 * response.data.lossesEnemy / response.data.gameCountEnemy}`);
                    drawsEnemy.style.width = `${100 * response.data.drawsEnemy / response.data.gameCountEnemy}%`;
                    drawsEnemy.setAttribute('aria-valuenow', `${100 * response.data.drawsEnemy / response.data.gameCountEnemy}`);

                    const hitsEnemyTotal = response.data.hitsEnemy + response.data.deathsEnemy;

                    hitsEnemy.style.width = hitsEnemyTotal === 0 ? '50%' : `${100 * response.data.hitsEnemy / hitsEnemyTotal}%`;
                    hitsEnemy.setAttribute('aria-valuenow', `${100 * response.data.hitsEnemy / hitsEnemyTotal}`);
                    deathsEnemy.style.width = hitsEnemyTotal === 0 ? '50%' : `${100 * response.data.deathsEnemy / hitsEnemyTotal}%`;
                    deathsEnemy.setAttribute('aria-valuenow', `${100 * response.data.deathsEnemy / hitsEnemyTotal}`);
                }

                totalGamesTogether.innerText = response.data.gameCount.toString();
                gamesCompareChart.data.datasets[0].data[0] = response.data.gameCountTogether;
                gamesCompareChart.data.datasets[0].data[1] = response.data.gameCountEnemyTeam;
                gamesCompareChart.data.datasets[0].data[2] = response.data.gameCountEnemySolo;
                gamesCompareChart.update();

                compareLoaded = true;
            })
            .catch(e => {
                console.error(e);
                stopLoading(false, true);
            })
    }
}