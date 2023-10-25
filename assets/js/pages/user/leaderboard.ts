import {initDataTableForm} from "../../components/dataTable";
import {initTableRowLink} from "../../functions";

export default function initUserLeaderBoardPage() {
	const form = document.getElementById('user-leaderboard-form') as HTMLFormElement;
	console.log(form);
    initDataTableForm(form, () => {
        initTableRowLink(form);
    });
}