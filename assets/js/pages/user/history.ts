import {initDataTableForm} from "../../components/dataTable";
import {startLoading, stopLoading} from "../../loaders";
import {removeGameFromProfile} from "../../api/endpoints/user";

export default function initUserHistory() {
	const form = document.getElementById('user-history-form') as HTMLFormElement;
    const csrfTokenInput = document.querySelector<HTMLInputElement>(
        '#user-game-remove-csrf input[name="_csrf_token"]'
    );
    const initTable = () => {
        const removeGameBtns = form.querySelectorAll('.remove-game-from-profile') as NodeListOf<HTMLButtonElement>;

        removeGameBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                if (!confirm(btn.dataset.confirm)) {
                    return;
                }
                const code = btn.dataset.code;
                const userId = Number(btn.dataset.user);
                const csrfToken = csrfTokenInput?.value ?? '';
                startLoading();
                removeGameFromProfile(code, userId, csrfToken)
                    .then(() => {
                        stopLoading(true);
                        btn.findParentElement('tr').remove();
                    })
                    .catch(e => {
                        console.error(e);
                        stopLoading(false);
                    })
            })
        })
    };
    initDataTableForm(
        form,
        initTable
    );
    initTable();
}
