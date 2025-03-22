import {initSetMeGroup} from "../userPlayer";
import {initDataTableForm} from "../components/dataTable";
import {startLoading, stopLoading} from "../loaders";
import {makePhotosHiddenGroup, makePhotosPublicGroup} from "../api/endpoints/game";
import {triggerNotificationError} from "../components/notifications";

declare global {
    const groupId : string;
}

export default function initResultsGroup() {
	initSetMeGroup();

    const groupGameList = document.getElementById('groupGameList') as HTMLFormElement;
    initDataTableForm(groupGameList);

    initPhotos();
}


function initPhotos() {
    const photos = document.querySelectorAll<HTMLImageElement>('.game-photo img');
    const dialog = document.getElementById('photo-dialog') as HTMLDialogElement;
    if (photos.length === 0 || !dialog) {
        console.log('Skip game photos')
        return;
    }

    const dialogImg = dialog.querySelector('img');
    const dialogWebpSource = dialog.querySelector<HTMLSourceElement>('.webp-source');

    for (const photo of photos) {
        const url = photo.src;
        const webp = photo.dataset.webp;

        photo.addEventListener('click', () => {
            dialogImg.src = url;
            dialogWebpSource.srcset = webp;
            dialog.showModal();
        });
    }

    dialog.addEventListener('click', () => {
        dialog.close();
    });

    const makePublic = document.getElementById('make-photos-public') as HTMLButtonElement;
    if (makePublic) {
        makePublic.addEventListener('click', () => {
            if (!confirm(makePublic.dataset.confirm)) {
                return;
            }
            startLoading(true);
            makePhotosPublicGroup(groupId)
                .then(() => {
                    window.location.reload();
                })
                .catch(async (e) => {
                    stopLoading(false, true);
                    await triggerNotificationError(e);
                });
        });
    }
    const makeHidden = document.getElementById('make-photos-hidden') as HTMLButtonElement;
    if (makeHidden) {
        makeHidden.addEventListener('click', () => {
            if (!confirm(makeHidden.dataset.confirm)) {
                return;
            }
            startLoading(true);
            makePhotosHiddenGroup(groupId)
                .then(() => {
                    window.location.reload();
                })
                .catch(async (e) => {
                    stopLoading(false, true);
                    await triggerNotificationError(e);
                });
        });
    }
}