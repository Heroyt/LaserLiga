import {initSetMeGroup} from "../userPlayer";
import {initDataTableForm} from "../components/dataTable";

export default function initResultsGroup() {
	initSetMeGroup();

    const groupGameList = document.getElementById('groupGameList') as HTMLFormElement;
    initDataTableForm(groupGameList);
}