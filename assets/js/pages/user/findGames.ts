import {startLoading, stopLoading} from "../../loaders";
import axios from "axios";

export default function initFindGames(): void {
	const lines = document.querySelectorAll('#user-possible-matches-table tbody tr') as NodeListOf<HTMLTableRowElement>;
	console.log(lines);

    const setAllMe = document.getElementById('set-all-me') as HTMLButtonElement;

    setAllMe.addEventListener('click', () => {
        if (!confirm(setAllMe.dataset.confirm)) {
            return;
        }

        startLoading();
        axios.post('/user/player/setallme', {})
            .then(() => {
                stopLoading(true);
                lines.forEach(line => {
                    line.remove();
                });
            })
            .catch(e => {
                console.error(e);
                stopLoading(false);
            })
    });

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