import {initDataTableForm} from "../../components/dataTable";

export default function initUserHistory() {
	const form = document.getElementById('user-history-form') as HTMLFormElement;
	initDataTableForm(form);
}