import {initUserAutocomplete} from "../../components/userPlayerSearch";
import {Collapse} from "bootstrap";

export default function initRegister() {
	const playerRows = document.querySelectorAll('.player-row') as NodeListOf<HTMLDivElement>;

	playerRows.forEach(playerRow => {
		const registerSwitch = playerRow.querySelector('.form-check-input') as HTMLInputElement;
		const playerSearch = playerRow.querySelector('input[type="search"]') as HTMLInputElement;
		const registerHideElems = playerRow.querySelectorAll('.registered-hide') as NodeListOf<HTMLDivElement>;
		const registerShowElems = playerRow.querySelectorAll('.registered-show') as NodeListOf<HTMLDivElement>;

		const playerUser = playerRow.querySelector('.player-user') as HTMLInputElement;
		const playerNickname = playerRow.querySelector('.player-nickname') as HTMLInputElement;
		const playerEmail = playerRow.querySelector('.player-email') as HTMLInputElement;
		const playerSkill = playerRow.querySelector('.player-skill') as HTMLSelectElement;

		initUserAutocomplete(playerSearch, (data) => {
			playerSearch.value = data.code + ' - ' + data.nickname;
			playerUser.value = data.code;
			playerNickname.value = data.nickname;
			playerEmail.value = data.email;
            if (data.stats.rank > 550) {
				playerSkill.value = 'PRO';
			} else if (data.stats.rank > 400) {
				playerSkill.value = 'ADVANCED';
			} else if (data.stats.rank > 200) {
				playerSkill.value = 'SOMEWHAT_ADVANCED';
			} else {
				playerSkill.value = 'BEGINNER';
			}
		});

		registerSwitch.addEventListener('change', () => {
			if (registerSwitch.checked) {
				registerHideElems.forEach(el => {
					el.classList.add('d-none');
				});
				registerShowElems.forEach(el => {
					el.classList.remove('d-none');
				});
			} else {
				registerHideElems.forEach(el => {
					el.classList.remove('d-none');
				});
				registerShowElems.forEach(el => {
					el.classList.add('d-none');
				});
			}
		});
	});

    const previousTeamSelect = document.getElementById('previousTeam') as HTMLSelectElement | undefined;
    if (previousTeamSelect) {
        const newTeamCollapseSection = document.getElementById('new-team-form') as HTMLDivElement;
        const newTeamCollapse = Collapse.getOrCreateInstance(newTeamCollapseSection, {toggle: false});

        previousTeamSelect.addEventListener('change', () => {
            if (previousTeamSelect.value === '') {
                newTeamCollapse.show();
            } else {
                newTeamCollapse.hide();
            }
        });
    }
}