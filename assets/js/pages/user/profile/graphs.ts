import {Chart} from "chart.js/auto";
import {startLoading, stopLoading} from "../../../loaders";
import axios, {AxiosResponse} from "axios";
import {graphColors} from "./constants";

export default function initGraphsTab(graphsTabBtn: HTMLAnchorElement, graphsTabWrapper: HTMLDivElement) {
    const graphsHistoryFilter = document.getElementById('graphsHistoryFilter') as HTMLSelectElement;
    const userCode = graphsTabBtn.dataset.user;
    const gameCountsCanvas = document.getElementById('games-graphs-graph') as HTMLCanvasElement;
    import(
        /* webpackChunkName: "date-local" */
        'date-fns/locale'
        )
        .then(localeModule => {
            const gameCountsChart = new Chart(gameCountsCanvas, {
                type: "bar", data: {
                    labels: [], datasets: [],
                }, options: {
                    maintainAspectRatio: false, responsive: true, scales: {
                        x: {
                            stacked: true,
                        }, y: {
                            stacked: true
                        }
                    }
                }
            });
            const rankOrderCanvas = document.getElementById('rank-order-graph') as HTMLCanvasElement;
            const rankOrderChart = new Chart(rankOrderCanvas, {
                type: "line", data: {
                    labels: [], datasets: [{
                        label: rankOrderCanvas.dataset.label, data: [],
                    },],
                }, options: {
                    maintainAspectRatio: false, responsive: true, scales: {
                        x: {
                            type: 'time', time: {
                                unit: 'day',
                            }, adapters: {
                                date: {
                                    locale: localeModule[document.documentElement.lang]
                                }
                            }
                        }, y: {
                            reverse: true, min: 1,
                        }
                    }
                }
            });

            const radarCanvas = document.getElementById('radar-graphs-graph') as HTMLCanvasElement;
            const radarCategories: { [index: string]: string } = JSON.parse(radarCanvas.dataset.categories);
            const radarCompare = radarCanvas.dataset.compare ?? '';
            const radarChart = new Chart(radarCanvas, {
                type: "radar", data: {
                    labels: Object.values(radarCategories), datasets: [],
                }, options: {
                    maintainAspectRatio: false, responsive: true, elements: {
                        line: {
                            borderWidth: 2,
                        }
                    }, scales: {
                        r: {
                            grid: {
                                display: true, color: '#777',
                            }, angleLines: {
                                display: true, color: '#aaa',
                            }, ticks: {
                                backdropColor: null, color: '#aaa',
                            }
                        }
                    }
                }
            });
            const graphsLoader = document.getElementById('graphs-loader') as HTMLDivElement;
            const graphsStatsWrapper = document.getElementById('graphs-stats') as HTMLDivElement;
            let graphsLoaded = false;
            if (graphsTabWrapper.classList.contains('show')) {
                loadGraphs();
            }
            graphsTabBtn.addEventListener('show.bs.tab', e => {
                loadGraphs();
            });
            graphsHistoryFilter.addEventListener('change', () => {
                loadGraphs();
            });

            function loadGraphs() {
                let loaded = 0;
                const graphCount = 3;
                startLoading(true);
                axios.get(`/user/${userCode}/stats/gamecounts?limit=${graphsHistoryFilter.value}`)
                    .then((response: AxiosResponse<{
                        [index: string]: {
                            label: string, modes: { count: number, id_mode: number, modeName: string }[]
                        }
                    }>) => {
                        if (!response.data) {
                            return;
                        }
                        loaded++;
                        if (!graphsLoaded) {
                            graphsLoader.classList.add('d-none');
                            graphsStatsWrapper.classList.remove('d-none');
                            graphsLoaded = true;
                        }
                        let datasets = new Map();
                        gameCountsChart.data.labels = [];
                        let i = 0;
                        Object.values(response.data).forEach((values) => {
                            gameCountsChart.data.labels.push(values.label);
                            values.modes.forEach(modeData => {
                                if (!datasets.has(modeData.id_mode)) {
                                    datasets.set(modeData.id_mode, {
                                        label: modeData.modeName,
                                        backgroundColor: graphColors[i % graphColors.length],
                                        data: [],
                                    })
                                    i++;
                                }
                                let data = datasets.get(modeData.id_mode);
                                data.data.push(modeData.count);
                                datasets.set(modeData.id_mode, data);
                            });
                        });
                        gameCountsChart.data.datasets = Array.from(datasets.values());
                        gameCountsChart.update();
                        if (loaded >= graphCount) {
                            stopLoading(true, true);
                        }
                    })
                    .catch(e => {
                        console.error(e);
                        stopLoading(false, true);
                    })
                axios.get(`/user/${userCode}/stats/rank/orderhistory?limit=${graphsHistoryFilter.value}`)
                    .then((response: AxiosResponse<{ [index: string]: { label: string, position: number } }>) => {
                        if (!response.data) {
                            return;
                        }
                        loaded++;
                        if (!graphsLoaded) {
                            graphsLoader.classList.add('d-none');
                            graphsStatsWrapper.classList.remove('d-none');
                            graphsLoaded = true;
                        }
                        rankOrderChart.data.labels = [];
                        rankOrderChart.data.datasets[0].data = [];
                        Object.entries(response.data).forEach(([date, values]) => {
                            //rankOrderChart.data.labels.push(values.label);
                            rankOrderChart.data.datasets[0].data.push({
                                x: date, y: values.position
                            });
                        });
                        rankOrderChart.update();
                        if (loaded >= graphCount) {
                            stopLoading(true, true);
                        }
                    })
                    .catch(e => {
                        console.error(e);
                        stopLoading(false, true);
                    })
                axios.get(`/user/${userCode}/stats/radar?compare=${radarCompare}`)
                    .then((response: AxiosResponse<{ [index: string]: { [index: string]: number } }>) => {
                        if (!response.data) {
                            return;
                        }
                        loaded++;
                        if (!graphsLoaded) {
                            graphsLoader.classList.add('d-none');
                            graphsStatsWrapper.classList.remove('d-none');
                            graphsLoaded = true;
                        }
                        radarChart.data.datasets = [];
                        let i = 0;
                        Object.entries(response.data).forEach(([label, values]) => {
                            radarChart.data.datasets.push({
                                label, data: Object.values(values),
                            });
                        });
                        radarChart.update();
                        if (loaded >= graphCount) {
                            stopLoading(true, true);
                        }
                    })
                    .catch(e => {
                        console.error(e);
                        stopLoading(false, true);
                    })
            }
        });
}