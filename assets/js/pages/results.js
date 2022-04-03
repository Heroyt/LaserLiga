import {Modal} from 'bootstrap';
import axios from "axios";

export default function initResults() {
	const leaderboardModalDom = document.getElementById('leaderboard-modal');
	if (!leaderboardModalDom) {
		return;
	}
	const leaderboardModalDomBody = leaderboardModalDom.querySelector('.modal-body');

	const leaderboardModal = new Modal(leaderboardModalDom);

	document.querySelectorAll('.show-leaderboard').forEach(btn => {
		const url = btn.dataset.href;
		btn.addEventListener('click', () => {
			axios.get(url)
				.then(response => {
					leaderboardModalDomBody.innerHTML = response.data;
					leaderboardModal.show();
				})
				.catch(response => {
					console.error(response.data);
					// TODO: Display error
				})
		});
	});
}