import {Chart} from "chart.js/auto";
import axios, {AxiosResponse} from "axios";
import 'chartjs-adapter-date-fns';
import {startLoading, stopLoading} from "../../loaders";
import {Tooltip} from "bootstrap";

interface TrendData {
	before: number,
	now: number,
	diff: number,
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
				const rankHistoryChart = new Chart(
					rankHistoryCanvas,
					{
						type: "line",
						data: {
							labels: ['Skill'],
							datasets: [
								{
									data: [],
									tension: 0.1,
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
				.then((response: AxiosResponse<{ gameCount: number, gameCountTogether: number, gameCountEnemy: number, gameCountEnemyTeam: number, gameCountEnemySolo: number, winsTogether: number, lossesTogether: number, drawsTogether: number, winsEnemy: number, lossesEnemy: number, drawsEnemy: number, hitsEnemy: number, deathsEnemy: number, hitsTogether: number, deathsTogether: number }>) => {
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
				.catch(() => {
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
				.then((response: AxiosResponse<{ accuracy: number, averageShots: number, rank: number, games: TrendData, rankableGames: TrendData, sumShots: TrendData, sumHits: TrendData, sumDeaths: TrendData }>) => {
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

					trendsLoaded = true;
				})
				.catch(e => {
					console.error(e);
					stopLoading(false, true);
				});
		});
	}

	const graphsTabBtn = document.getElementById('graphs-tab') as HTMLLIElement | null;
	const graphsTabWrapper = document.getElementById('graphs-stats-tab') as HTMLDivElement | null;
	if (graphsTabBtn && graphsTabWrapper) {
		const graphsHistoryFilter = document.getElementById('graphsHistoryFilter') as HTMLSelectElement;
		const userCode = graphsTabBtn.dataset.user;
		const gameCountsCanvas = document.getElementById('games-graphs-graph') as HTMLCanvasElement;
		const gameCountsChart = new Chart(
			gameCountsCanvas,
			{
				type: "bar",
				data: {
					labels: [],
					datasets: [],
				},
				options: {
					maintainAspectRatio: false,
					responsive: true,
					scales: {
						x: {
							stacked: true,
						},
						y: {
							stacked: true
						}
					}
				}
			}
		);
		const graphsLoader = document.getElementById('graphs-loader') as HTMLDivElement;
		const graphsStatsWrapper = document.getElementById('graphs-stats') as HTMLDivElement;
		let graphsLoaded = false;
		graphsTabBtn.addEventListener('show.bs.tab', e => {
			loadGraphs();
		});

		graphsHistoryFilter.addEventListener('change', () => {
			loadGraphs();
		});

		function loadGraphs() {
			startLoading(true);
			axios.get(`/user/${userCode}/stats/gamecounts?limit=${graphsHistoryFilter.value}`)
				.then((response: AxiosResponse<{ [index: string]: { label: string, modes: { count: number, id_mode: number, modeName: string }[] } }>) => {
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
								datasets.set(
									modeData.id_mode,
									{
										label: modeData.modeName,
										backgroundColor: colors[i % colors.length],
										data: [],
									}
								)
								i++;
							}
							let data = datasets.get(modeData.id_mode);
							data.data.push(modeData.count);
							datasets.set(modeData.id_mode, data);
						});
					});
					gameCountsChart.data.datasets = Array.from(datasets.values());
					gameCountsChart.update();
					stopLoading(true, true);
				})
				.catch(e => {
					console.error(e);
					stopLoading(false, true);
				})
		}
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