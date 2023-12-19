import {startLoading, stopLoading} from "../../../loaders";
import {Tooltip} from "bootstrap";
import {getUserTrends} from "../../../api/endpoints/userStats";

export default function initTrendsTab(trendsTabBtn: HTMLAnchorElement, trendsTabWrapper: HTMLDivElement): void {
    let trendsLoaded = false;

    const trendsLoaderWrapper = document.getElementById('trends-loader') as HTMLDivElement;
    const trendsStatsWrapper = document.getElementById('trends-stats') as HTMLDivElement;

    const code = trendsTabBtn.dataset.user;
    if (trendsTabWrapper.classList.contains('show')) {
        loadTrends();
    }
    trendsTabBtn.addEventListener('show.bs.tab', () => {
        if (trendsLoaded) {
            return; // Do not load data more than once
        }
        loadTrends();
    });

    function loadTrends() {
        startLoading(true);
        getUserTrends(code)
            .then(response => {
                stopLoading(true, true);
                trendsLoaderWrapper.classList.add('d-none');
                trendsStatsWrapper.classList.remove('d-none');

                initTrend(document.getElementById('rank-trend') as HTMLDivElement, response.rank);
                initTrend(document.getElementById('accuracy-trend') as HTMLDivElement, response.accuracy);
                initTrend(document.getElementById('average-shots-trend') as HTMLDivElement, response.averageShots);
                initTrend(document.getElementById('game-count-trend') as HTMLDivElement, response.games.diff);
                initTrend(document.getElementById('rankable-game-count-trend') as HTMLDivElement, response.rankableGames.diff);
                initTrend(document.getElementById('sum-shots-trend') as HTMLDivElement, response.sumShots.diff);
                initTrend(document.getElementById('sum-hits-trend') as HTMLDivElement, response.sumHits.diff);
                initTrend(document.getElementById('sum-deaths-trend') as HTMLDivElement, response.sumDeaths.diff);
                initTrend(document.getElementById('rank-order-trend') as HTMLDivElement, response.rankOrder.diff);

                trendsLoaded = true;
            })
            .catch(e => {
                console.error(e);
                stopLoading(false, true);
            });
    }
}

function initTrend(elem: HTMLDivElement, value: number) {
    let tooltipContent: string;
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