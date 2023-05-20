import {initUserAutocomplete} from "../../components/userPlayerSearch";

interface PlayerData {
	name: string,
	user: number | null,
	team: number,
	hits: number,
	deaths: number,
	hitsOwn: number,
	deathsOwn: number,
	shots: number,
	accuracy: number,
	mineHits: number,
	agent: number,
	invisibility: number,
	machineGun: number,
	shield: number,
}

export default function initCreateGame() {
	const teams: Map<number, { name: string, color: number }> = new Map();
	const players: Map<number, PlayerData> = new Map();

	const teamWrapper = document.getElementById('team-wrapper') as HTMLDivElement;
	const playerWrapper = document.getElementById('player-wrapper') as HTMLDivElement;
	const addTeam = document.getElementById('addTeam') as HTMLButtonElement;
	const addPlayer = document.getElementById('addPlayer') as HTMLButtonElement;

	const hitsTable = document.querySelector('#player-hits') as HTMLTableElement;
	const hitsHead = document.getElementById('player-hits-head') as HTMLTableRowElement;
	const hitsBody = document.querySelector('#player-hits tbody') as HTMLTableSectionElement;

	const debug = document.getElementById('debugPrint') as HTMLButtonElement;

	debug.addEventListener('click', () => {
		console.log(teams, players);
	});

	let teamId = 0;
	addTeam.addEventListener('click', () => {
		createTeam(teamId);
		teamId++;
	});

	let playerId = 0;
	addPlayer.addEventListener('click', () => {
		createPlayer(playerId);
		playerId++;
	});


	hitsTable.addEventListener('hits-change', () => {
		console.log('hits-change');
		players.forEach((playerData, playerId) => {
			const hitsInputs = hitsTable.querySelectorAll(`input[data-id="${playerId}"]`) as NodeListOf<HTMLInputElement>;
			const deathsInputs = hitsTable.querySelectorAll(`input[data-target="${playerId}"]`) as NodeListOf<HTMLInputElement>;
			playerData.hits = 0;
			playerData.hitsOwn = 0;
			playerData.deaths = 0;
			playerData.deathsOwn = 0;

			hitsInputs.forEach(input => {
				const id = parseInt(input.dataset.target);
				const target = players.get(id);
				const value = parseInt(input.value);
				playerData.hits += value;
				if (target.team === playerData.team) {
					playerData.hitsOwn += value;
				}
			});

			deathsInputs.forEach(input => {
				const id = parseInt(input.dataset.id);
				const target = players.get(id);
				const value = parseInt(input.value);
				playerData.deaths += value;
				if (target.team === playerData.team) {
					playerData.deathsOwn += value;
				}
			});

			players.set(playerId, playerData);

			const hitsDom = document.getElementById(`player-${playerId}-hits`) as HTMLSpanElement;
			hitsDom.innerText = playerData.hits.toString();
			const hitsOwnDom = document.getElementById(`player-${playerId}-hitsOwn`) as HTMLSpanElement;
			hitsOwnDom.innerText = playerData.hitsOwn.toString();
			const deathsDom = document.getElementById(`player-${playerId}-deaths`) as HTMLSpanElement;
			deathsDom.innerText = playerData.deaths.toString();
			const deathsOwnDom = document.getElementById(`player-${playerId}-deathsOwn`) as HTMLSpanElement;
			deathsOwnDom.innerText = playerData.deathsOwn.toString();
		});
	})

	function createPlayer(playerId: number) {
		let data: PlayerData = {
			name: '',
			user: null,
			team: 0,
			hits: 0,
			deaths: 0,
			hitsOwn: 0,
			deathsOwn: 0,
			shots: 0,
			accuracy: 0,
			mineHits: 0,
			agent: 0,
			invisibility: 0,
			machineGun: 0,
			shield: 0,
		};

		players.set(playerId, data);
		const wrapper = document.createElement('div');
		wrapper.classList.add('my-3', 'input-group');
		wrapper.innerHTML = `<input type="hidden" id="player-${playerId}-user" name="players[${playerId}][user]">` +
			`<div class="form-floating">` +
			`<input type="text" class="form-control" id="player-${playerId}-name" placeholder="Jméno" name="players[${playerId}][name]">` +
			`<label for="player-${playerId}-name">Jméno</label>` +
			`</div>` +
			`<div class="form-floating">` +
			`<select class="form-select team-select" id="player-${playerId}-team" name="players[${playerId}][team]"></select>` +
			`<label for="player-${playerId}-team">Tým</label>` +
			`</div>` +
			`<div class="form-floating">` +
			`<input type="number" value="0" min="0" class="form-control" id="player-${playerId}-shots" placeholder="Výstřely" name="players[${playerId}][shots]">` +
			`<label for="player-${playerId}-shots">Výstřely</label>` +
			`</div>` +
			`<div class="form-floating">` +
			`<input type="number" value="0" min="0" max="100" class="form-control" id="player-${playerId}-accuracy" placeholder="Přesnost" name="players[${playerId}][accuracy]">` +
			`<label for="player-${playerId}-accuracy">Přesnost</label>` +
			`</div>` +
			`<div class="input-group-text">Zásahy: <span id="player-${playerId}-hits">0</span></div>` +
			`<div class="input-group-text">Zásahy vlastních: <span id="player-${playerId}-hitsOwn">0</span></div>` +
			`<div class="input-group-text">Smrti: <span id="player-${playerId}-deaths">0</span></div>` +
			`<div class="input-group-text">Smrti od vlastních: <span id="player-${playerId}-deathsOwn">0</span></div>` +
			`<div class="form-floating">` +
			`<input type="number" value="0" min="0" class="form-control" id="player-${playerId}-mineHits" placeholder="Zásahy" name="players[${playerId}][mineHits]">` +
			`<label for="player-${playerId}-mineHits">Zásahy od miny</label>` +
			`</div>` +
			`<div class="form-floating">` +
			`<input type="number" value="0" min="0" class="form-control" id="player-${playerId}-agent" placeholder="Agent" name="players[${playerId}][agent]">` +
			`<label for="player-${playerId}-agent">Agent</label>` +
			`</div>` +
			`<div class="form-floating">` +
			`<input type="number" value="0" min="0" class="form-control" id="player-${playerId}-invisibility" placeholder="Neviditelnost" name="players[${playerId}][invisibility]">` +
			`<label for="player-${playerId}-invisibility">Neviditelnost</label>` +
			`</div>` +
			`<div class="form-floating">` +
			`<input type="number" value="0" min="0" class="form-control" id="player-${playerId}-machineGun" placeholder="Samopal" name="players[${playerId}][machineGun]">` +
			`<label for="player-${playerId}-machineGun">Samopal</label>` +
			`</div>` +
			`<div class="form-floating">` +
			`<input type="number" value="0" min="0" class="form-control" id="player-${playerId}-shield" placeholder="Štít" name="players[${playerId}][shield]">` +
			`<label for="player-${playerId}-shield">Štít</label>` +
			`</div>`;

		const hitsRow = document.createElement('tr');
		hitsRow.dataset.id = playerId.toString();
		const hitsRowHead = document.createElement('th');
		const hitsHeadCell = document.createElement('th');
		hitsRow.appendChild(hitsRowHead);
		hitsHead.appendChild(hitsHeadCell);
		players.forEach((playerData, id) => {
			const cell = document.createElement('td');
			if (id !== playerId) {
				cell.innerHTML = `<input type="number" min="0" name="hits[${playerId}][${id}]" value="0" class="form-control" data-target="${id}" data-id="${playerId}">`;
				const input = cell.querySelector('input');
				input.addEventListener('input', () => {
					hitsTable.dispatchEvent(new CustomEvent('hits-change'));
				});
			}
			hitsRow.appendChild(cell);
		});

		hitsBody.querySelectorAll('tr').forEach(row => {
			const id = parseInt(row.dataset.id);
			const cell = document.createElement('td');
			cell.innerHTML = `<input type="number" min="0" name="hits[${id}][${playerId}]" value="0" class="form-control" data-target="${playerId}" data-id="${id}">`;
			const input = cell.querySelector('input');
			input.addEventListener('input', () => {
				hitsTable.dispatchEvent(new CustomEvent('hits-change'));
			});
			row.appendChild(cell);
		});

		hitsBody.appendChild(hitsRow);

		const user = wrapper.querySelector(`#player-${playerId}-user`) as HTMLInputElement;
		const name = wrapper.querySelector(`#player-${playerId}-name`) as HTMLInputElement;
		name.addEventListener('input', () => {
			data.name = name.value;
			hitsRowHead.innerText = data.name;
			hitsHeadCell.innerText = data.name;
			data.user = null;
			user.value = '';
			players.set(playerId, data);
		});
		initUserAutocomplete(name, userData => {
			data.name = userData.nickname;
			data.user = userData.id;
			hitsRowHead.innerText = data.name;
			hitsHeadCell.innerText = data.name;
			name.value = data.name;
			user.value = data.user.toString();
			players.set(playerId, data);
		});

		const team = wrapper.querySelector(`#player-${playerId}-team`) as HTMLSelectElement;
		teams.forEach((teamData, id) => {
			const option = document.createElement('option');
			option.value = id.toString();
			option.innerText = teamData.name;
			team.appendChild(option);
		});
		team.addEventListener('change', () => {
			data.team = parseInt(team.value);
			players.set(playerId, data);
		});

		playerWrapper.appendChild(wrapper);
	}

	function createTeam(teamId: number) {
		const wrapper = document.createElement('div');
		wrapper.classList.add('input-group', 'my-3');

		let data = {name: '', color: 0};

		wrapper.innerHTML = `<div class="form-floating">` +
			`<input type="text" class="form-control" id="team-${teamId}-name" placeholder="Jméno" name="teams[${teamId}][name]">` +
			`<label for="team-${teamId}-name">Jméno</label>` +
			`</div>` +
			`<div class="form-floating">` +
			`<select class="form-select" id="team-${teamId}-color" name="teams[${teamId}][color]"><option value="0">Červená</option><option value="1">Zelená</option><option value="2">Modrá</option><option value="3">Růžová</option><option value="4">Žlutá</option><option value="5">Oceánová</option></select>` +
			`<label for="team-${teamId}-color">Barva</label>` +
			`</div>`;

		const name = wrapper.querySelector(`#team-${teamId}-name`) as HTMLInputElement;
		const color = wrapper.querySelector(`#team-${teamId}-color`) as HTMLSelectElement;

		name.addEventListener('input', () => {
			data.name = name.value;
			teams.set(teamId, data);
		});
		color.addEventListener('input', () => {
			data.color = parseInt(color.value);
			teams.set(teamId, data);
		});

		playerWrapper.querySelectorAll('.team-select').forEach(select => {
			const option = document.createElement('option');
			option.value = teamId.toString();
			option.innerText = data.name;
			select.appendChild(option);
		});

		teams.set(teamId, data);
		teamWrapper.appendChild(wrapper);
	}
}