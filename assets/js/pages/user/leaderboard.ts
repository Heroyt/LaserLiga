import {initDataTableForm} from "../../components/dataTable";

export default function initUserLeaderBoardPage() {
	const form = document.getElementById('user-leaderboard-form') as HTMLFormElement;
	console.log(form);
	initDataTableForm(form);
}