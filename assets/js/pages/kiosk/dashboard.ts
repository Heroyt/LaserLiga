import {initDataTableForm} from "../../components/dataTable";
import {fetchGet} from "../../api/client";
import {Tooltip} from "bootstrap";
import {ArcElement, Chart, Colors, DoughnutController, Legend, Tooltip as ChartTooltip} from "chart.js";
import {getArenaStatsModes, getArenaStatsMusic} from "../../api/endpoints/arena";
import {initTableRowLink, initTooltips} from "../../functions";
import {initUserAutocomplete} from "../../components/userPlayerSearch";

Chart.register(Colors, Legend, DoughnutController, ArcElement, ChartTooltip);
Chart.defaults.color = '#fff';

declare global {
    const restartLogoutTimer: () => void;
}

export default function initKioskDashboard() {
    const wrapper = document.getElementById('kiosk-wrapper') as HTMLDivElement;
    let audio = new Map<string, HTMLAudioElement>;
    let reloadTimer: NodeJS.Timeout;
    let arenaId: number;
    const preload = new Map<string, Promise<string>>;
    const cache = new Map<string, string>;
    if (wrapper) {
        arenaId = parseInt(wrapper.dataset.id);
        initKioskLinks(wrapper, wrapper);
        const nav = document.getElementById('mobile-menu-full');
        if (nav) {
            initKioskLinks(wrapper, nav);
        }
    }
    initGames();
    initMusic();
    initStats();
    initLeaderboard();
    initSearch();

    function initKioskLinks(wrapper: HTMLDivElement, linkWrapper : HTMLElement) {
        const links = linkWrapper.querySelectorAll<HTMLAnchorElement>('a.kiosk-link');
        for (const link of links) {
            const useCache = link.classList.contains('kiosk-link-cache');
            link.addEventListener('mouseover', () => {
                if (preload.has(link.href)) {
                    return;
                }
                preload.set(link.href, fetchGet(link.href))
            });
            link.addEventListener('click', (e) => {
                e.preventDefault();
                window.history.pushState({}, null, link.href);

                if (cache.has(link.href)) {
                    const response = cache.get(link.href);
                    if (!document.startViewTransition) {
                        replaceContent(response);
                        initAfterLoad();
                        return;
                    }
                    document.startViewTransition(() => {
                        replaceContent(response);
                        initAfterLoad();
                    });
                    return;
                }

                let load: Promise<string>;
                if (preload.has(link.href)) {
                    load = preload.get(link.href);
                    preload.delete(link.href);
                } else {
                    load = fetchGet(link.href);
                }

                load.then(response => {
                    if (useCache) {
                        cache.set(link.href, response);
                    }
                    if (!document.startViewTransition) {
                        replaceContent(response);
                        initAfterLoad();
                        return;
                    }
                    document.startViewTransition(() => {
                        replaceContent(response);
                        initAfterLoad();
                    });
                });
            })
        }

        const href = window.location.href;
        // Don't reload on cached pages
        if (!cache.has(href)) {
            if (reloadTimer) {
                clearTimeout(reloadTimer);
            }
            reloadTimer = setTimeout(() => {
                fetchGet(href).then(response => {
                    if (!document.startViewTransition) {
                        replaceContent(response);
                        initAfterLoad(false);
                        return;
                    }
                    document.startViewTransition(() => {
                        replaceContent(response);
                        initAfterLoad(false);
                    });
                });
            }, 60_000);
        }

        function replaceContent(response: string) {
            const tmp = document.createElement('div');
            tmp.innerHTML = response;
            const wrapperTmp = tmp.querySelector<HTMLDivElement>('#kiosk-wrapper')
            if (wrapperTmp) {
                response = wrapperTmp.innerHTML;
            }
            wrapper.innerHTML = response;
        }

        function initAfterLoad(restartLogout : boolean = true) {
            initTableRowLink(wrapper);
            initTooltips(wrapper);
            initKioskLinks(wrapper, wrapper);
            initGames();
            initMusic();
            initStats();
            initLeaderboard();
            initSearch();
            if ('restartLogoutTimer' in window && restartLogout) {
                restartLogoutTimer();
            }
        }
    }

    function initSearch() {
        const input = document.getElementById('kiosk-user-search') as HTMLInputElement;
        if (!input) {
            return;
        }

        initUserAutocomplete(input, user => {
           window.location.href = '/user/' + user.code;
        });
    }

    function initGames() {
        const form = document.getElementById('arena-history-form') as HTMLFormElement;
        if (!form) {
            return;
        }
        initDataTableForm(form);
    }

    function initLeaderboard() {
        const form = document.getElementById('user-leaderboard-form') as HTMLFormElement;
        if (!form) {
            return;
        }
        initDataTableForm(form);
    }

    function initMusic() {
        for (const [file, audioElem] of audio) {
            if (!audioElem.paused) {
                audioElem.pause();
            }
        }
        (document.querySelectorAll('.music') as NodeListOf<HTMLDivElement>).forEach(initMusicPlay);
    }

    function initMusicPlay(elem: HTMLDivElement) {
        const playBtn = elem.querySelector('.play-music') as HTMLButtonElement;
        if (playBtn) {
            const playLabel = playBtn.dataset.play;
            const stopLabel = playBtn.dataset.stop;
            const media = playBtn.dataset.file;
            let audioElem = audio.get(media);
            const tooltip = Tooltip.getInstance(playBtn);
            playBtn.addEventListener('click', () => {
                playBtn.classList.add('loading');
                console.log(media);
                if (!audioElem) {
                    audioElem = new Audio(media);
                    audio.set(media, audioElem);
                    audioElem.load();
                    console.log(audioElem);
                }

                if (!audioElem.paused) {
                    pause();
                    return;
                }

                if (audioElem.readyState === HTMLMediaElement.HAVE_ENOUGH_DATA) {
                    triggerPlay();
                } else {
                    audioElem.addEventListener('canplaythrough', triggerPlay);
                }

                audioElem.addEventListener('ended', pause);
            });

            function pause() {
                playBtn.classList.add('btn-success');
                playBtn.classList.remove('btn-danger', 'loading', 'playing');
                if (tooltip) {
                    tooltip.setContent({
                        '.tooltip-inner': playLabel,
                    });
                }
                // Stop
                audioElem.pause();
            }

            function triggerPlay() {
                const timeWrap = elem.querySelector('.time-music') as HTMLDivElement;
                if (audioElem.paused) {
                    audioElem.addEventListener('timeupdate', () => {
                        timeWrap.innerText = `${Math.floor(audioElem.currentTime / 60)}:${Math.floor(audioElem.currentTime % 60).toString().padStart(2, '0')}`;
                    });
                    playBtn.classList.remove('btn-success', 'loading');
                    playBtn.classList.add('btn-danger', 'playing');
                    if (tooltip) {
                        tooltip.setContent({
                            '.tooltip-inner': stopLabel,
                        });
                    }
                    // Play
                    audioElem.play();
                }
            }
        }
    }

    function initStats() {
        const graphFilter = document.getElementById('graph-filter') as HTMLSelectElement;
        const gameModesCanvas = document.getElementById('gameModes') as HTMLCanvasElement;
        const musicModesCanvas = document.getElementById('musicModes') as HTMLCanvasElement;

        if (!graphFilter || !gameModesCanvas || !musicModesCanvas) {
            return;
        }

        const date = graphFilter.dataset.date;

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

        const gameModesChart = new Chart(gameModesCanvas, {
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
                plugins: {
                    legend: {
                        labels: {

                        },
                        position: 'bottom',
                    }
                }
            }
        });
        const musicModesChart = new Chart(musicModesCanvas, {
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
                plugins: {
                    legend: {
                        labels: {

                        },
                        position: 'bottom',
                    }
                }
            }
        });

        loadGraphs();
        graphFilter.addEventListener('change', loadGraphs);

        function loadGraphs() {
            let params = new URLSearchParams;
            switch (graphFilter.value) {
                case 'date':
                    params.set('date', date);
                    break;
                case 'week':
                    params.set('week', date);
                    break;
                case 'month':
                    params.set('month', date);
                    break;
            }
            getArenaStatsModes(arenaId, params)
                .then(response => {
                    gameModesChart.data.labels = [];
                    gameModesChart.data.datasets[0].data = [];
                    Object.entries(response).forEach(([label, count]) => {
                        gameModesChart.data.labels.push(label);
                        gameModesChart.data.datasets[0].data.push(count);
                    });
                    gameModesChart.update();
                });
            getArenaStatsMusic(arenaId, params)
                .then(response => {
                    musicModesChart.data.labels = [];
                    musicModesChart.data.datasets[0].data = [];
                    Object.entries(response).forEach(([label, count]) => {
                        musicModesChart.data.labels.push(label);
                        musicModesChart.data.datasets[0].data.push(count);
                    });
                    musicModesChart.update();
                });
        }
    }
}