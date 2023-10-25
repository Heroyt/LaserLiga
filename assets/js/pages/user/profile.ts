import {Chart} from "chart.js/auto";
import axios, {AxiosResponse} from "axios";
import 'chartjs-adapter-date-fns';
import {startLoading, stopLoading} from "../../loaders";
import {Tooltip} from "bootstrap";
import {initTooltips} from "../../functions";

interface TrendData {
    before: number,
    now: number,
    diff: number,
}

interface Achievement {
    id: number;
    icon: string | null;
    name: string;
    description: string;
    info: string | null;
    type: string;
    rarity: string;
    key: string | null;
    group: boolean;
}

interface AchievementClaimDto {
    achievement: Achievement;
    icon: string;
    claimed: boolean;
    code: string | null;
    dateTime: { date: string, timezone_type: number, timezone: string } | null;
    totalCount: number;
}

export default function initProfile() {
    const rankHistoryCanvas = document.getElementById('rankHistory') as HTMLCanvasElement;
    const gameModesCanvas = document.getElementById('gameModes') as HTMLCanvasElement;

    const colors = [
        'rgb(255, 99, 132)',
        'rgb(54, 162, 235)',
        'rgb(255, 205, 86)',
        'rgb(86,255,89)',
        'rgb(128,86,255)',
        'rgb(86,255,190)',
        'rgb(255,137,86)',
        'rgb(238,86,255)',
        'rgb(73,101,215)',
        'rgb(208,55,55)',
        'rgb(57,190,36)',
    ];

    if (rankHistoryCanvas && gameModesCanvas) {
        const rankHistoryFilter = document.getElementById('rankHistoryFilter') as HTMLSelectElement;
        const id = rankHistoryCanvas.dataset.user;
        import(
            /* webpackChunkName: "date-local" */
            'date-fns/locale'
            )
            .then(localeModule => {
                console.log(localeModule[document.documentElement.lang]);
                const compareRankHistoryBtn = document.getElementById('compareRankHistory') as HTMLButtonElement | null;
                let compareUser = '';
                let compareEnabled = false;
                if (compareRankHistoryBtn) {
                    compareUser = compareRankHistoryBtn.dataset.user;
                    compareRankHistoryBtn.addEventListener('click', () => {
                        compareEnabled = !compareEnabled;
                        loadGraphs();
                    });
                }
                const rankHistoryChart = new Chart(
                    rankHistoryCanvas,
                    {
                        type: "line",
                        data: {
                            labels: ['Skill'],
                            datasets: [
                                {
                                    label: rankHistoryCanvas.dataset.label,
                                    data: [],
                                    tension: 0.1,
                                    borderColor: colors[1],
                                }
                            ],
                        },
                        options: {
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false,
                                }
                            },
                            scales: {
                                x: {
                                    type: 'time',
                                    time: {
                                        unit: 'day',
                                    },
                                    adapters: {
                                        date: {
                                            locale: localeModule[document.documentElement.lang]
                                        }
                                    }
                                }
                            }
                        }
                    }
                );
                const gameModesChart = new Chart(
                    gameModesCanvas,
                    {
                        type: "doughnut",
                        data: {
                            labels: [],
                            datasets: [
                                {
                                    data: [],
                                    backgroundColor: colors,
                                    borderWidth: 0,
                                }
                            ],
                        },
                        options: {
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                }
                            }
                        }
                    }
                );

                loadGraphs();

                rankHistoryFilter.addEventListener('change', loadGraphs);

                function loadGraphs() {
                    axios.get('/user/' + id + '/stats/rankhistory?limit=' + rankHistoryFilter.value)
                        .then((response: AxiosResponse<{ [index: string]: number }>) => {
                            rankHistoryChart.data.labels = [];
                            rankHistoryChart.data.datasets[0].data = [];
                            Object.entries(response.data).forEach(([date, count]) => {
                                // @ts-ignore
                                rankHistoryChart.data.datasets[0].data.push({x: date, y: count});
                            });
                            rankHistoryChart.update();
                        });
                    if (compareEnabled && compareUser) {
                        if (rankHistoryChart.data.datasets[1]) {
                            rankHistoryChart.show(1);
                        }
                        axios.get('/user/' + compareUser + '/stats/rankhistory?limit=' + rankHistoryFilter.value)
                            .then((response: AxiosResponse<{ [index: string]: number }>) => {
                                compareRankHistoryBtn.classList.remove('btn-outline-info');
                                compareRankHistoryBtn.classList.add('btn-info');
                                rankHistoryChart.data.datasets[1] = {
                                    label: compareRankHistoryBtn.dataset.label,
                                    data: [],
                                    tension: 0.1,
                                    borderColor: colors[0],
                                };
                                rankHistoryChart.data.datasets[1].data = [];
                                Object.entries(response.data).forEach(([date, count]) => {
                                    // @ts-ignore
                                    rankHistoryChart.data.datasets[1].data.push({x: date, y: count});
                                });
                                rankHistoryChart.update();
                            });
                    } else if (rankHistoryChart.data.datasets[1]) {
                        compareRankHistoryBtn.classList.add('btn-outline-info');
                        compareRankHistoryBtn.classList.remove('btn-info');
                        rankHistoryChart.hide(1);
                    }
                    axios.get('/user/' + id + '/stats/modes?limit=' + rankHistoryFilter.value)
                        .then((response: AxiosResponse<{ [index: string]: number }>) => {
                            gameModesChart.data.labels = [];
                            gameModesChart.data.datasets[0].data = [];
                            Object.entries(response.data).forEach(([label, count]) => {
                                gameModesChart.data.labels.push(label);
                                gameModesChart.data.datasets[0].data.push(count);
                            });
                            gameModesChart.update();
                        });
                }
            });
    }

    const compareTabBtn = document.getElementById('compare-tab') as HTMLLIElement | null;
    const compareTabWrapper = document.getElementById('compare-stats-tab') as HTMLDivElement | null;
    if (compareTabBtn && compareTabWrapper) {
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

        const gamesCompareChart = new Chart(
            gamesCompareCanvas,
            {
                type: "doughnut",
                data: {
                    labels: [
                        gamesCompareCanvas.dataset.labelTogether,
                        gamesCompareCanvas.dataset.labelEnemyTeam,
                        gamesCompareCanvas.dataset.labelEnemySolo,
                    ],
                    datasets: [
                        {
                            data: [],
                            backgroundColor: [
                                'rgb(54, 162, 235)',
                                'rgb(255, 99, 132)',
                                'rgb(255, 205, 86)',
                            ],
                            borderWidth: 0,
                        }
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            }
        );
        const totalGamesTogether = document.getElementById('total-games-together') as HTMLSpanElement;

        const code = compareTabBtn.dataset.user;
        compareTabBtn.addEventListener('show.bs.tab', e => {
            if (compareLoaded) {
                return; // Do not load data more than once
            }
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
        });
    }

    const trendsTabBtn = document.getElementById('trends-tab') as HTMLLIElement | null;
    const trendsTabWrapper = document.getElementById('trends-stats-tab') as HTMLDivElement | null;
    if (trendsTabBtn && trendsTabWrapper) {
        let trendsLoaded = false;

        const trendsLoaderWrapper = document.getElementById('trends-loader') as HTMLDivElement;
        const trendsStatsWrapper = document.getElementById('trends-stats') as HTMLDivElement;

        const code = trendsTabBtn.dataset.user;
        trendsTabBtn.addEventListener('show.bs.tab', e => {
            if (trendsLoaded) {
                return; // Do not load data more than once
            }
            startLoading(true);
            axios.get('/user/' + code + '/stats/trends')
                .then((response: AxiosResponse<{
                    accuracy: number,
                    averageShots: number,
                    rank: number,
                    games: TrendData,
                    rankableGames: TrendData,
                    sumShots: TrendData,
                    sumHits: TrendData,
                    sumDeaths: TrendData,
                    rankOrder: TrendData
                }>) => {
                    stopLoading(true, true);
                    trendsLoaderWrapper.classList.add('d-none');
                    trendsStatsWrapper.classList.remove('d-none');

                    initTrend(
                        document.getElementById('rank-trend') as HTMLDivElement,
                        response.data.rank
                    );
                    initTrend(
                        document.getElementById('accuracy-trend') as HTMLDivElement,
                        response.data.accuracy
                    );
                    initTrend(
                        document.getElementById('average-shots-trend') as HTMLDivElement,
                        response.data.averageShots
                    );
                    initTrend(
                        document.getElementById('game-count-trend') as HTMLDivElement,
                        response.data.games.diff
                    );
                    initTrend(
                        document.getElementById('rankable-game-count-trend') as HTMLDivElement,
                        response.data.rankableGames.diff
                    );
                    initTrend(
                        document.getElementById('sum-shots-trend') as HTMLDivElement,
                        response.data.sumShots.diff
                    );
                    initTrend(
                        document.getElementById('sum-hits-trend') as HTMLDivElement,
                        response.data.sumHits.diff
                    );
                    initTrend(
                        document.getElementById('sum-deaths-trend') as HTMLDivElement,
                        response.data.sumDeaths.diff
                    );
                    initTrend(
                        document.getElementById('rank-order-trend') as HTMLDivElement,
                        response.data.rankOrder.diff
                    );

                    trendsLoaded = true;
                })
                .catch(e => {
                    console.error(e);
                    stopLoading(false, true);
                });
        });
    }

    const trophiesTabBtn = document.getElementById('trophies-tab') as HTMLLIElement | null;
    const trophiesTabWrapper = document.getElementById('trophies-stats-tab') as HTMLDivElement | null;
    if (trophiesTabBtn && trophiesTabWrapper) {
        let trophiesLoaded = false;

        const trophiesLoaderWrapper = document.getElementById('trophies-loader') as HTMLDivElement;
        const trophiesStatsWrapper = document.getElementById('trophies-stats') as HTMLDivElement;
        const trophiesWrapper = document.getElementById('trophies-wrapper') as HTMLDivElement;

        const trophiesAllModesCheck = document.getElementById('trophies-all-modes') as HTMLInputElement;
        const trophiesRankableModesCheck = document.getElementById('trophies-rankable-modes') as HTMLInputElement;

        const code = trophiesTabBtn.dataset.user;
        trophiesTabBtn.addEventListener('show.bs.tab', e => {
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
            axios.get('/user/' + code + '/stats/trophies' + (trophiesRankableModesCheck.checked ? '?rankable=1' : ''))
                .then((response: AxiosResponse<{
                    [index: string]: { name: string, icon: string, description: string, count: number }
                }>) => {
                    stopLoading(true, true);
                    trophiesLoaderWrapper.classList.add('d-none');
                    trophiesStatsWrapper.classList.remove('d-none');

                    trophiesWrapper.innerHTML = '';
                    Object.entries(response.data).forEach(([key, trophy]) => {
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

    const achievementsTabBtn = document.getElementById('achievements-tab') as HTMLLIElement | null;
    const achievementsTabWrapper = document.getElementById('achievements-stats-tab') as HTMLDivElement | null;
    if (achievementsTabBtn && achievementsTabWrapper) {
        let achievementsLoaded = false;

        const achievementsLoaderWrapper = document.getElementById('achievements-loader') as HTMLDivElement;
        const achievementsStatsWrapper = document.getElementById('achievements-stats') as HTMLDivElement;
        const achievementsWrapper = document.getElementById('achievements-wrapper') as HTMLDivElement;
        const achievementsUnclaimedWrapper = document.getElementById('achievements-unclaimed-wrapper') as HTMLDivElement;

        const achievementsClaimedCount = document.querySelector('.achievements-claimed-count') as HTMLSpanElement;
        const achievementsCount = document.querySelector('.achievements-count') as HTMLSpanElement;

        const playerCount = parseInt(achievementsStatsWrapper.dataset.playerCount);
        const claimLabel = achievementsWrapper.dataset.claimLabel;
        const percentageLabel = achievementsWrapper.dataset.percentageLabel;
        const code = achievementsTabBtn.dataset.user;
        achievementsTabBtn.addEventListener('show.bs.tab', e => {
            if (achievementsLoaded) {
                return; // Do not load data more than once
            }
            updateAchievements();
        });

        function updateAchievements() {
            startLoading(true);
            achievementsWrapper.innerHTML = '';
            achievementsUnclaimedWrapper.innerHTML = '';
            axios.get('/user/' + code + '/stats/achievements')
                .then((response: AxiosResponse<AchievementClaimDto[]>) => {
                    stopLoading(true, true);
                    achievementsLoaderWrapper.classList.add('d-none');
                    achievementsStatsWrapper.classList.remove('d-none');

                    achievementsCount.innerText = response.data.length.toString();

                    const achievementGroups: Map<string, HTMLDivElement> = new Map;
                    let claimed = 0;
                    response.data.forEach(achievement => {
                        const achievementEl = document.createElement('div');
                        achievementEl.classList.add('achievement-card', 'm-2', 'rarity-' + achievement.achievement.rarity, achievement.claimed ? 'achievement-claimed' : 'achievement-unclaimed');
                        achievementEl.id = 'achievement-' + achievement.achievement.id.toString();
                        let infoBtn = '';
                        if (achievement.achievement.info) {
                            infoBtn = `<button class="btn p-0" type="button" data-toggle="tooltip" title="${achievement.achievement.info}"><i class="fa-solid fa-circle-question fs-5" style="line-height: 1.2rem;vertical-align: middle;"></i></button>`;
                        }
                        achievementEl.innerHTML = `${achievement.icon}<h4 class="title">${achievement.achievement.name}</h4><p class="description">${achievement.achievement.description}${infoBtn}</p>`;
                        achievementEl.innerHTML += `<p class="claim-percent">${percentageLabel.replace('%s', (achievement.totalCount / playerCount).toLocaleString(undefined, {
                            style: 'percent',
                            maximumFractionDigits: 2
                        }))}</p>`;
                        if (achievement.claimed && achievement.dateTime && achievement.code) {
                            claimed++;
                            const date = new Date(achievement.dateTime.date);
                            achievementEl.innerHTML += `<div class="claim-info">${claimLabel}: <a href="/g/${achievement.code}" class="btn btn-secondary">${date.toLocaleDateString()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}</a></div>`;
                        }

                        if (!achievement.claimed) {
                            achievementsUnclaimedWrapper.appendChild(achievementEl);
                        } else if (achievement.achievement.group) {
                            let group = achievementGroups.get(achievement.achievement.type);
                            if (!group) {
                                group = document.createElement('div');
                                achievementGroups.set(achievement.achievement.type, group);
                                group.classList.add('achievement-group', 'm-3');
                                group.dataset.group = achievement.achievement.type;
                                group.setAttribute('data-group', achievement.achievement.type);
                                achievementsWrapper.appendChild(group);

                                // Rotate cards on click
                                group.addEventListener('click', e => {
                                    if (e.target instanceof HTMLAnchorElement || e.target instanceof HTMLButtonElement || group.childElementCount < 2) {
                                        return;
                                    }

                                    const el = group.lastElementChild;
                                    el.classList.add('move-back', 'animating');

                                    setTimeout(() => {
                                        group.prepend(el);
                                        setTimeout(() => {
                                            el.classList.remove('move-back');
                                            setTimeout(() => {
                                                el.classList.remove('animating');
                                            }, 300);
                                        }, 5);
                                    }, 200)
                                });
                            }
                            group.appendChild(achievementEl);
                        } else {
                            achievementsWrapper.appendChild(achievementEl);
                        }
                    });
                    achievementsClaimedCount.innerText = claimed.toString();
                    initTooltips(achievementsWrapper);
                    initTooltips(achievementsUnclaimedWrapper);

                    achievementsLoaded = true;
                })
                .catch(e => {
                    console.error(e);
                    stopLoading(false, true);
                });
        }
    }

    const graphsTabBtn = document.getElementById('graphs-tab') as HTMLLIElement | null;
    const graphsTabWrapper = document.getElementById('graphs-stats-tab') as HTMLDivElement | null;
    if (graphsTabBtn && graphsTabWrapper) {
        import(
            /* webpackChunkName: "profile-graphs" */
            './profile/graphs'
            ).then(module => {
            module.default(graphsTabBtn, colors);
        });
    }

    function initTrend(elem: HTMLDivElement, value: number) {
        let tooltipContent: string = '';
        if (value > 1) {
            elem.classList.remove('falling', 'stable');
            elem.classList.add('rising');
            tooltipContent = elem.dataset.tooltipRising;
        } else if (value < -1) {
            elem.classList.remove('rising', 'stable');
            elem.classList.add('falling');
            tooltipContent = elem.dataset.tooltipFalling;
        } else {
            elem.classList.remove('rising', 'falling');
            elem.classList.add('stable');
            tooltipContent = elem.dataset.tooltipStable;
        }

        const valueElem = elem.querySelector('.trend-value') as HTMLDivElement;
        valueElem.innerText = (value > 0 ? '+' : (value == 0 ? '+-' : '')) + (Math.round(value * 100) / 100);

        new Tooltip(elem, {title: tooltipContent});
    }
}