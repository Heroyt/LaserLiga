import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Colors,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    RadarController,
    RadialLinearScale,
    TimeScale,
    Tooltip
} from "chart.js";
import 'chartjs-adapter-date-fns';
import {startLoading, stopLoading} from "../../../loaders";
import {graphColors} from "./constants";
import cs from "date-fns/locale/cs";
import enGB from "date-fns/locale/en-GB";
import {getUserGameCounts, getUserOrderHistory, getUserRadar} from "../../../api/endpoints/userStats";

Chart.register(
    RadarController,
    LineController,
    LineElement,
    PointElement,
    RadialLinearScale,
    LinearScale,
    CategoryScale,
    Colors,
    TimeScale,
    Legend,
    BarController,
    BarElement,
    Tooltip
)

export default function initGraphsTab(graphsTabBtn: HTMLAnchorElement, graphsTabWrapper: HTMLDivElement) {
    const graphsHistoryFilter = document.getElementById('graphsHistoryFilter') as HTMLSelectElement;
    const userCode = graphsTabBtn.dataset.user;
    const gameCountsCanvas = document.getElementById('games-graphs-graph') as HTMLCanvasElement;
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
                            locale: ['cs', 'sk'].includes(document.documentElement.lang) ? cs : enGB,
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
        type: "radar",
        data: {
            labels: Object.values(radarCategories), datasets: [],
        },
        options: {
            parsing: {
                key: 'value'
            },
            maintainAspectRatio: false, responsive: true, elements: {
                line: {
                    borderWidth: 2,
                }
            },
            scales: {
                r: {
                    grid: {
                        display: true, color: '#777',
                    }, angleLines: {
                        display: true, color: '#aaa',
                    }, ticks: {
                        backdropColor: null, color: '#aaa',
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            console.log(context);
                            let label = context.dataset.label || '';

                            if (label) {
                                label += ': ';
                            }
                            // @ts-ignore
                            if (context.raw.label) {
                                // @ts-ignore
                                label += context.raw.label;
                            } else if (context.parsed.r !== null) {
                                label += context.parsed.r.toLocaleString();
                            }
                            // @ts-ignore
                            if (context.raw.percentileLabel) {
                                // @ts-ignore
                                label += ` (${context.raw.percentileLabel})`
                            }
                            return label;
                        }
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
    graphsTabBtn.addEventListener('show.bs.tab', () => {
        loadGraphs();
    });
    graphsHistoryFilter.addEventListener('change', () => {
        loadGraphs();
    });

    function loadGraphs() {
        let loaded = 0;
        const graphCount = 3;
        startLoading(true);
        getUserGameCounts(userCode, graphsHistoryFilter.value)
            .then(response => {
                if (!response) {
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
                Object.values(response).forEach((values) => {
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
            });
        getUserOrderHistory(userCode, graphsHistoryFilter.value)
            .then(response => {
                if (!response) {
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
                Object.entries(response).forEach(([date, values]) => {
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
        getUserRadar(userCode, radarCompare)
            .then(response => {
                if (!response) {
                    return;
                }
                loaded++;
                if (!graphsLoaded) {
                    graphsLoader.classList.add('d-none');
                    graphsStatsWrapper.classList.remove('d-none');
                    graphsLoaded = true;
                }
                radarChart.data.datasets = [];
                Object.entries(response).forEach(([label, values]) => {
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
}