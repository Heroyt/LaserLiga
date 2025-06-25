import { startLoading, stopLoading } from "../../../loaders";
import GameCountChart from "../../../components/charts/gameCountsChart";
import RankOrderChart from "../../../components/charts/rankOrderChart";
import UserRadarChart from "../../../components/charts/userRadarChart";

export default function initGraphsTab(graphsTabBtn: HTMLAnchorElement, graphsTabWrapper: HTMLDivElement) {
    const graphsHistoryFilter = document.getElementById('graphsHistoryFilter') as HTMLSelectElement;
    const userCode = graphsTabBtn.dataset.user;

    const gameCountsCanvas = document.getElementById('games-graphs-graph') as HTMLCanvasElement;
    const gameCountsChart = new GameCountChart(gameCountsCanvas, userCode);

    const rankOrderCanvas = document.getElementById('rank-order-graph') as HTMLCanvasElement;
    const rankOrderChart = new RankOrderChart(rankOrderCanvas, userCode);

    const radarCanvas = document.getElementById('radar-graphs-graph') as HTMLCanvasElement;
    const radarCompare = radarCanvas.dataset.compare ?? '';
    const radarChart = new UserRadarChart(radarCanvas, userCode, radarCompare);

    const graphsLoader = document.getElementById('graphs-loader') as HTMLDivElement;
    const graphsStatsWrapper = document.getElementById('graphs-stats') as HTMLDivElement;
    let graphsLoaded = false;
    if (graphsTabWrapper.classList.contains('show')) {
        loadGraphs();
    }
    graphsTabBtn.addEventListener('show.bs.tab', loadGraphs);
    graphsHistoryFilter.addEventListener('change',loadGraphs)

    function loadGraphs() {
        let loaded = 0;
        const graphCount = 3;
        startLoading(true);
        gameCountsChart.load(graphsHistoryFilter.value)
            .then(() => {
                loaded++;
                if (!graphsLoaded) {
                    graphsLoader.classList.add('d-none');
                    graphsStatsWrapper.classList.remove('d-none');
                    graphsLoaded = true;
                }
                if (loaded >= graphCount) {
                    stopLoading(true, true);
                }
            })
            .catch(e => {
                console.error(e);
                stopLoading(false, true);
            });
        rankOrderChart.load(graphsHistoryFilter.value)
            .then(() => {
                loaded++;
                if (!graphsLoaded) {
                    graphsLoader.classList.add('d-none');
                    graphsStatsWrapper.classList.remove('d-none');
                    graphsLoaded = true;
                }
                if (loaded >= graphCount) {
                    stopLoading(true, true);
                }
            })
            .catch(e => {
                console.error(e);
                stopLoading(false, true);
            });
        radarChart.load()
            .then(() => {
                loaded++;
                if (!graphsLoaded) {
                    graphsLoader.classList.add('d-none');
                    graphsStatsWrapper.classList.remove('d-none');
                    graphsLoaded = true;
                }
                if (loaded >= graphCount) {
                    stopLoading(true, true);
                }
            })
            .catch(e => {
                console.error(e);
                stopLoading(false, true);
            });
    }
}