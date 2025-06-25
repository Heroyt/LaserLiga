import RankHistoryChart from "../../../components/charts/rankHistoryChart";
import GameModesCountChart from "../../../components/charts/gameModesCountChart";

export default async function initGeneralTab(generalTabBtn: HTMLAnchorElement, generalTabWrapper: HTMLDivElement): Promise<void> {
    let generalLoaded = false;

    const rankHistoryCanvas = document.getElementById("rankHistory") as HTMLCanvasElement;
    const gameModesCanvas = document.getElementById("gameModes") as HTMLCanvasElement;
    const rankHistoryFilter = document.getElementById("rankHistoryFilter") as HTMLSelectElement;

    const code = rankHistoryCanvas.dataset.user;

    const compareRankHistoryBtn = document.getElementById("compareRankHistory") as HTMLButtonElement | null;
    if (compareRankHistoryBtn) {
        compareRankHistoryBtn.addEventListener("click", () => {
            rankHistoryChart.toggleCompare(compareRankHistoryBtn.dataset.user);
        });
    }

    const rankHistoryChart = new RankHistoryChart(rankHistoryCanvas, code, compareRankHistoryBtn);
    const gameModesChart = new GameModesCountChart(gameModesCanvas, code);

    if (generalTabWrapper.classList.contains("show")) {
        loadGraphs();
    }
    generalTabBtn.addEventListener("show.bs.tab", () => {
        if (generalLoaded) {
            return; // Do not load data more than once
        }
        loadGraphs();
    });

    rankHistoryFilter.addEventListener("change", loadGraphs);

    function loadGraphs() {
        rankHistoryChart.load(rankHistoryFilter.value);
        gameModesChart.load(rankHistoryFilter.value);
        generalLoaded = true;
    }
}