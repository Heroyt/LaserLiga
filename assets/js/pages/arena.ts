import {Chart} from "chart.js/auto";
import axios, {AxiosResponse} from "axios";
import {Tooltip} from "bootstrap";

interface PageInfo {
	type: 'GET' | 'POST',
	routeName?: string,
	path: string[]
	params: { [index: string]: string | number }
}

declare global {
	const page: PageInfo
}

export default function initArena() {
	const gameModesCanvas = document.getElementById('gameModes') as HTMLCanvasElement;
	const musicModesCanvas = document.getElementById('musicModes') as HTMLCanvasElement;

	const dateFilter = document.getElementById('date-select') as HTMLSelectElement;
	const graphFilter = document.getElementById('graph-filter') as HTMLSelectElement;

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
				plugins: {
					legend: {
						position: 'bottom',
					}
				}
			}
		}
	);
	const musicModesChart = new Chart(
		musicModesCanvas,
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
				plugins: {
					legend: {
						position: 'bottom',
					}
				}
			}
		}
	);

	loadGraphs();
	graphFilter.addEventListener('change', loadGraphs);
	dateFilter.addEventListener('change', () => {
		window.location.href = window.location.origin + window.location.pathname + `?date=${dateFilter.value}`;
	});

	(document.querySelectorAll('.music') as NodeListOf<HTMLDivElement>).forEach(initMusic);

	function initMusic(elem: HTMLDivElement) {
		const id = elem.dataset.id;
		const playBtn = elem.querySelector('.play-music') as HTMLButtonElement;
		if (playBtn) {
			const playLabel = playBtn.dataset.play;
			const stopLabel = playBtn.dataset.stop;
			const media = playBtn.dataset.file;
			let audio: HTMLAudioElement;
			const tooltip = Tooltip.getInstance(playBtn);
			playBtn.addEventListener('click', () => {
				playBtn.innerHTML = `<div class="spinner-grow spinner-grow-sm" role="status"><span class="visually-hidden">Loading...</span></div>`;
				if (!audio) {
					audio = new Audio(media);
					audio.load();
					console.log(audio);
				}

				if (!audio.paused) {
					playBtn.classList.add('btn-success');
					playBtn.classList.remove('btn-danger');
					playBtn.innerHTML = `<i class="fa-solid fa-play"></i>`;
					tooltip.setContent({
						'.tooltip-inner': playLabel,
					});
					// Stop
					audio.pause();
					return;
				}

				if (audio.readyState === HTMLMediaElement.HAVE_ENOUGH_DATA) {
					triggerPlay();
				} else {
					audio.addEventListener('canplaythrough', triggerPlay);
				}
			});

			function triggerPlay() {
				const timeWrap = elem.querySelector('.time-music') as HTMLDivElement;
				if (audio.paused) {
					audio.addEventListener('timeupdate', () => {
						timeWrap.innerText = `${Math.floor(audio.currentTime / 60)}:${Math.floor(audio.currentTime % 60).toString().padStart(2, '0')}`;
					});
					playBtn.classList.remove('btn-success');
					playBtn.classList.add('btn-danger');
					playBtn.innerHTML = `<i class="fa-solid fa-stop"></i>`;
					tooltip.setContent({
						'.tooltip-inner': stopLabel,
					});
					// Play
					audio.play();
				}
			}
		}
	}

	function loadGraphs() {
		let params: string[] = [];
		switch (graphFilter.value) {
			case 'date':
				params.push('date=' + dateFilter.value)
				break;
			case 'week':
				params.push('week=' + dateFilter.value)
				break;
			case 'month':
				params.push('month=' + dateFilter.value)
				break;
		}
		axios.get('/arena/' + page.params.id + '/stats/modes' + (params.length > 0 ? '?' + params.join('&') : ''))
			.then((response: AxiosResponse<{ [index: string]: number }>) => {
				gameModesChart.data.labels = [];
				gameModesChart.data.datasets[0].data = [];
				Object.entries(response.data).forEach(([label, count]) => {
					gameModesChart.data.labels.push(label);
					gameModesChart.data.datasets[0].data.push(count);
				});
				gameModesChart.update();
			});
		axios.get('/arena/' + page.params.id + '/stats/music' + (params.length > 0 ? '?' + params.join('&') : ''))
			.then((response: AxiosResponse<{ [index: string]: number }>) => {
				musicModesChart.data.labels = [];
				musicModesChart.data.datasets[0].data = [];
				Object.entries(response.data).forEach(([label, count]) => {
					musicModesChart.data.labels.push(label);
					musicModesChart.data.datasets[0].data.push(count);
				});
				musicModesChart.update();
			});
	}
}