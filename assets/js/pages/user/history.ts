import {initDataTableForm} from "../../components/dataTable";
import {startLoading, stopLoading} from "../../loaders";
import {userUnsetMe} from "../../api/endpoints/user";

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
                userUnsetMe(code)
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