import {startLoading, stopLoading} from "../../loaders";
import axios from "axios";

export default function initFindGames(): void {
	const lines = document.querySelectorAll('#user-possible-matches-table tbody tr') as NodeListOf<HTMLTableRowElement>;
	console.log(lines);

	lines.forEach(line => {
		const setMe = line.querySelector('.setMe') as HTMLButtonElement;
		const setNotMe = line.querySelector('.setNotMe') as HTMLButtonElement;
		const matchId = line.dataset.id;
		const playerId = setMe.dataset.id;
		const system = setMe.dataset.system;
		console.log(matchId, playerId, system, setMe, setNotMe)

		setMe.addEventListener('click', () => {
			startLoading();
			axios.post('/user/player/setme', {id: playerId, system})
				.then(() => {
					stopLoading(true);
					line.remove();
				})
				.catch(e => {
					console.error(e);
					stopLoading(false);
				});
		});

		setNotMe.addEventListener('click', () => {
			startLoading();
			axios.post('/user/player/setnotme', {id: matchId})
				.then(() => {
					stopLoading(true);
					line.remove();
				})
				.catch(e => {
					console.error(e);
					stopLoading(false);
				});
		});
	});
}