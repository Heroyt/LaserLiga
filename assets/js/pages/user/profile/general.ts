import {
    ArcElement,
    CategoryScale,
    Chart,
    Colors,
    DoughnutController,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    TimeScale,
    Tooltip
} from "chart.js";
import 'chartjs-adapter-date-fns';
import {graphColors} from "./constants";
import cs from "date-fns/locale/cs";
import enGB from "date-fns/locale/en-GB";
import {getUserModes, getUserRankHistory} from "../../../api/endpoints/userStats";

Chart.register(
    Colors,
    LinearScale,
    LineController,
    Legend,
    LineElement,
    PointElement,
    CategoryScale,
    DoughnutController,
    ArcElement,
    TimeScale,
    Tooltip
)

export default function initGeneralTab(generalTabBtn: HTMLAnchorElement, generalTabWrapper: HTMLDivElement): void {
    let generalLoaded = false;
    const rankHistoryCanvas = document.getElementById('rankHistory') as HTMLCanvasElement;
    const gameModesCanvas = document.getElementById('gameModes') as HTMLCanvasElement;
    const rankHistoryFilter = document.getElementById('rankHistoryFilter') as HTMLSelectElement;
    const code = rankHistoryCanvas.dataset.user;
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
    const rankHistoryChart = new Chart(rankHistoryCanvas, {
        type: "line", data: {
            labels: ['Skill'], datasets: [{
                label: rankHistoryCanvas.dataset.label, data: [], tension: 0.1, borderColor: graphColors[1],
            }],
        }, options: {
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
                    }, adapters: {
                        date: {
                            locale: ['cs', 'sk'].includes(document.documentElement.lang) ? cs : enGB,
                        }
                    }
                }
            }
        }
    });
    const gameModesChart = new Chart(gameModesCanvas, {
        type: "doughnut", data: {
            labels: [], datasets: [{
                data: [], backgroundColor: graphColors, borderWidth: 0,
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });

    if (generalTabWrapper.classList.contains('show')) {
        loadGraphs();
    }
    generalTabBtn.addEventListener('show.bs.tab', () => {
        if (generalLoaded) {
            return; // Do not load data more than once
        }
        loadGraphs();
    });

    rankHistoryFilter.addEventListener('change', loadGraphs);

    function loadGraphs() {
        getUserRankHistory(code, rankHistoryFilter.value)
            .then(response => {
                rankHistoryChart.data.labels = [];
                rankHistoryChart.data.datasets[0].data = [];
                Object.entries(response).forEach(([date, count]) => {
                    // @ts-ignore
                    rankHistoryChart.data.datasets[0].data.push({x: date, y: count});
                });
                rankHistoryChart.update();
            });
        if (compareEnabled && compareUser) {
            if (rankHistoryChart.data.datasets[1]) {
                rankHistoryChart.show(1);
            }
            getUserRankHistory(compareUser, rankHistoryFilter.value)
                .then(response => {
                    compareRankHistoryBtn.classList.remove('btn-outline-info');
                    compareRankHistoryBtn.classList.add('btn-info');
                    rankHistoryChart.data.datasets[1] = {
                        label: compareRankHistoryBtn.dataset.label,
                        data: [],
                        tension: 0.1,
                        borderColor: graphColors[0],
                    };
                    rankHistoryChart.data.datasets[1].data = [];
                    Object.entries(response).forEach(([date, count]) => {
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
        getUserModes(code, rankHistoryFilter.value)
            .then(response => {
                gameModesChart.data.labels = [];
                gameModesChart.data.datasets[0].data = [];
                Object.entries(response).forEach(([label, count]) => {
                    gameModesChart.data.labels.push(label);
                    gameModesChart.data.datasets[0].data.push(count);
                });
                gameModesChart.update();
            });
        generalLoaded = true;
    }
}