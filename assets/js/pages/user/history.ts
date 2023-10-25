import {initDataTableForm} from "../../components/dataTable";
import axios from "axios";
import {startLoading, stopLoading} from "../../loaders";

export default function initUserHistory() {
	const form = document.getElementById('user-history-form') as HTMLFormElement;
    const initTable = () => {
        const unsetMeBtns = form.querySelectorAll('.unset-me') as NodeListOf<HTMLButtonElement>;

        unsetMeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                if (!confirm(btn.dataset.confirm)) {
                    return;
                }
                const code = btn.dataset.code;
                startLoading();
                axios.post('/user/player/unsetme', {code})
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