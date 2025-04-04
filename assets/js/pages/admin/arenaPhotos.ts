import {startLoading, stopLoading} from "../../loaders";
import {
    assignGamePhotos,
    deletePhoto,
    deletePhotos,
    sendPhotosMail,
    setPhotosSecret,
    unassignGamePhotos
} from "../../api/endpoints/admin";
import {triggerNotification, triggerNotificationError} from "../../components/notifications";
import {initNativeDialog} from "../../components/dialog";

declare global {
    const arenaId : number;
}

export default function initPhotos() {
    // Filters
    const dateInput = document.getElementById("selected-date") as HTMLInputElement;
    const filterPhotosInput = document.getElementById("filter-photos") as HTMLInputElement;
    dateInput.addEventListener("change", () => {
        window.location.href = `?date=${dateInput.value}&filterphotos=${filterPhotosInput.checked ? '1' : '0'}`;
    });
    filterPhotosInput.addEventListener("change", () => {
        window.location.href = `?date=${dateInput.value}&filterphotos=${filterPhotosInput.checked ? '1' : '0'}`;
    });

    // Util vars
    let selectedGame = '';
    const selectedPhotos = new Set<HTMLElement>();

    // Game groups
    const gameGroups = document.querySelectorAll<HTMLDivElement>('.game-group');

    // Bulk delete
    const deleteBulkBtn = document.getElementById('delete-bulk') as HTMLButtonElement;

    // Photo dialog
    const dialog = document.getElementById('photo-dialog') as HTMLDialogElement;
    dialog.addEventListener('click', () => {
        dialog.close();
    });
    const dialogImg = dialog.querySelector('img');
    const dialogWebpSource = dialog.querySelector<HTMLSourceElement>('.webp-source');

    // Other dialogs
    const mailDialog = document.getElementById('send-mail-dialog') as HTMLDialogElement;
    const unassignDialog = document.getElementById('unassign-photos') as HTMLDialogElement;
    const unassignPhotosWrapper = unassignDialog.querySelector<HTMLDivElement>('.photos-wrapper');
    const unassignPhotosSubmit = unassignDialog.querySelector<HTMLButtonElement>('#unassign-photos-submit');
    const selectedUnassignedPhotos = new Set<number>();

    // Init parts
    initAssignPhotos();
    initGameGroups();
    initMailDialog();
    initUnassignDialog();

    // Helper functions
    function initGameGroups() : void {
        for (const gameGroup of gameGroups) {
            const assignPhotosBtn = gameGroup.querySelector<HTMLButtonElement>('.assign-photos');
            const sendEmailBtn = gameGroup.querySelector<HTMLButtonElement>('.send-email');
            const unassignPhotosBtn = gameGroup.querySelector<HTMLButtonElement>('.unassign-photos');
            const photosWrapper = gameGroup.querySelector<HTMLDivElement>('.group-photos');
            const showPhotos = gameGroup.querySelector<HTMLDivElement>('.show-photos');

            for (const img of photosWrapper.querySelectorAll<HTMLImageElement>('img')) {
                photoDialog(img);
            }

            const firstCode = assignPhotosBtn.dataset.code;
            const allCodes = JSON.parse(assignPhotosBtn.dataset.codes) as string[];

            assignPhotosBtn.addEventListener('click', async () => {
                if (selectedPhotos.size === 0) {
                    assignPhotosBtn.disabled = true;
                }
                if (assignPhotosBtn.disabled) {
                    return;
                }

                startLoading();
                try {
                    // Assign photos to the first game
                    const response = await assignGamePhotos(
                        arenaId,
                        firstCode,
                        Array.from(selectedPhotos, (photo) => parseInt(photo.dataset.id))
                    );

                    // Update photo secret of all games
                    await setPhotosSecret(arenaId, allCodes, response.values.secret);
                    stopLoading();

                    // Move pictures into game
                    for (const photo of selectedPhotos) {
                        const picture = photo.querySelector<HTMLPictureElement>('picture');
                        photosWrapper.appendChild(picture);
                        photo.remove();
                        photoDialog(picture.querySelector('img'));
                    }
                    selectedPhotos.clear();
                    onPhotoSelect();

                    sendEmailBtn.disabled = photosWrapper.childNodes.length === 0;
                    unassignPhotosBtn.disabled = sendEmailBtn.disabled;
                    if (sendEmailBtn.disabled) {
                        showPhotos.classList.add('d-none');
                    }
                    else {
                        showPhotos.classList.remove('d-none');
                    }
                } catch (e) {
                    stopLoading(false);
                    triggerNotificationError(e);
                }
            });
            sendEmailBtn.addEventListener('click', () => {
                selectedGame = firstCode;
                mailDialog.showModal();
            });
            unassignPhotosBtn.addEventListener('click', () => {
                unassignPhotosStart(gameGroup);
            });
        }
    }

    function unassignPhotosStart(group : HTMLDivElement) : void {
        const photos = group.querySelectorAll<HTMLPictureElement>('.game-photo');
        unassignPhotosWrapper.innerHTML = '';
        selectedUnassignedPhotos.clear();
        unassignPhotosSubmit.disabled = true;

        for (const photo of photos) {
            const id = parseInt(photo.dataset.id);
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'photos[]';
            input.id=`photo-select-${id}`;
            input.value = id.toString();
            input.classList.add('photo-select');
            const label = document.createElement('label');
            label.setAttribute('for', input.id);
            label.classList.add('border', 'rounded', 'p-2');
            const figure = document.createElement('figure');
            figure.classList.add('select-photo', 'figure', 'm-2');
            figure.appendChild(input);
            figure.appendChild(label);
            label.appendChild(photo.cloneNode(true));
            unassignPhotosWrapper.appendChild(figure);

            input.addEventListener('change', (e) => {
               if (input.checked) {
                   selectedUnassignedPhotos.add(id);
               }
               else {
                   selectedUnassignedPhotos.delete(id);
               }

                unassignPhotosSubmit.disabled = selectedUnassignedPhotos.size === 0;
            });
        }

        unassignDialog.showModal();
    }

    function initAssignPhotos() : void {
        const photos = document.querySelectorAll<HTMLElement>(".unassigned-photo");
        for (const photo of photos) {
            const input = photo.querySelector<HTMLInputElement>(".photo-select");
            const picture = photo.querySelector('picture');
            const deleteBtn = photo.querySelector<HTMLButtonElement>('.delete');
            const showBtn = photo.querySelector<HTMLButtonElement>('.show');

            input.addEventListener("change", () => {
                if (input.checked) {
                    selectedPhotos.add(photo);
                }
                else {
                    selectedPhotos.delete(photo);
                }
                onPhotoSelect();
            });

            if (deleteBtn) {
                deleteBtn.addEventListener("click", async () => {
                    if (!confirm(deleteBtn.dataset.confirm)) {
                        return false;
                    }
                    startLoading();
                    try {
                        await deletePhoto(arenaId, parseInt(photo.dataset.id));
                        stopLoading();
                        photo.remove();
                    } catch (e) {
                        stopLoading(false);
                        await triggerNotificationError(e);
                    }
                });
            }

            if (showBtn) {
                const image = picture.querySelector('img');
                const url = image.src;
                const webp = image.dataset.webp;
                showBtn.addEventListener("click", () => {
                    dialogImg.src = url;
                    dialogWebpSource.srcset = webp;
                    dialog.showModal();
                });
            }
        }
        deleteBulkBtn.addEventListener('click', async () => {
            if (selectedPhotos.size === 0) {
                return;
            }
            if (!confirm(deleteBulkBtn.dataset.confirm.replace('%d', selectedPhotos.size.toString()))) {
                return;
            }
            startLoading();
            const ids = Array.from(selectedPhotos, (photo) => parseInt(photo.dataset.id));
            try {
                await deletePhotos(arenaId, ids);
                stopLoading();
                for (const photo of selectedPhotos) {
                    photo.remove();
                }
                selectedPhotos.clear();
                onPhotoSelect();
            } catch (e) {
                stopLoading(false);
                await triggerNotificationError(e);
            }
        })

        const selectAllBtn = document.getElementById('select-all') as HTMLButtonElement;
        selectAllBtn.addEventListener('click', () => {
            for (const photo of photos) {
                const input = photo.querySelector<HTMLInputElement>('.photo-select');
                input.checked = true;
                selectedPhotos.add(photo);
            }
            onPhotoSelect();
        });
    }

    function initUnassignDialog() : void {
        initNativeDialog(unassignDialog);

        const form = unassignDialog.querySelector('form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (selectedUnassignedPhotos.size === 0) {
                return;
            }

            startLoading();
            try {
                await unassignGamePhotos(arenaId, Array.from(selectedUnassignedPhotos));
                stopLoading(true);
                window.location.reload();
            } catch (e) {
                stopLoading(false);
                triggerNotificationError(e);
            }
        });

        const selectAllBtn = unassignDialog.querySelector<HTMLButtonElement>('#unassign-select-all');
        selectAllBtn.addEventListener('click', () => {
           const inputs = unassignDialog.querySelectorAll('input');
           const check = inputs.length > selectedUnassignedPhotos.size;
            for (const input of inputs) {
                input.checked = check;
                if (input.checked) {
                    selectedUnassignedPhotos.add(parseInt(input.value));
                }
                else {
                    selectedUnassignedPhotos.delete(parseInt(input.value));
                }
            }
            unassignPhotosSubmit.disabled = selectedUnassignedPhotos.size === 0;
        });
    }

    function initMailDialog() : void {
        initNativeDialog(mailDialog);
        const mailsForm = mailDialog.querySelector<HTMLFormElement>('#mails-form');
        const mailInput = mailDialog.querySelector<HTMLTextAreaElement>('#mails');
        const sendMailBtn = mailDialog.querySelector<HTMLButtonElement>('#send-mail');

        mailsForm.addEventListener('submit', e => {
            e.preventDefault();
            sendEmails();
        });

        mailInput.addEventListener('input', e => {
            validateEmails();
        });

        function validateEmails() : string[] {
            // Parse emails from input (separator: new line or comma)
            const emails = mailInput.value.split(/[;,\n]/).map((email) => email.trim());
            // Validate emails
            const invalidFeedback = mailsForm.querySelector<HTMLDivElement>('.invalid-feedback');
            if (emails.length < 1) {
                invalidFeedback.innerText = mailInput.dataset.empty;
                mailInput.setCustomValidity(mailInput.dataset.empty);
                mailsForm.classList.add('was-validated');
                sendMailBtn.disabled = true;
                return [];
            }
            for (const email of emails) {
                if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    invalidFeedback.innerText = mailInput.dataset.invalid;
                    mailInput.setCustomValidity(mailInput.dataset.invalid);
                    mailsForm.classList.add('was-validated');
                    sendMailBtn.disabled = true;
                    return [];
                }
            }

            mailInput.setCustomValidity('');
            mailsForm.classList.add('was-validated');
            sendMailBtn.disabled = false;

            return emails;
        }

        async function sendEmails() {
            const emails = validateEmails();

            if (emails.length === 0) {
                return; // Invalid
            }

            if (!selectedGame) {
                triggerNotification({
                    type: 'danger',
                    content: mailsForm.dataset.failed,
                });
                return;
            }

            startLoading();
            try {
                await sendPhotosMail(arenaId, selectedGame, emails);
                stopLoading();
                mailDialog.close();
                mailInput.value='';
                mailsForm.classList.remove('was-validated');
                triggerNotification({
                    type: 'success',
                    content: mailsForm.dataset.success,
                });
            } catch (e) {
                stopLoading(false);
                await triggerNotificationError(e);
            }
        }
    }

    function onPhotoSelect(){
        for (const gameGroup of gameGroups) {
            gameGroup.querySelector<HTMLButtonElement>(".assign-photos").disabled = selectedPhotos.size === 0;
        }
        deleteBulkBtn.disabled = selectedPhotos.size === 0;
    }

    function photoDialog(photo : HTMLImageElement) : void {
        const url = photo.src;
        const webp = photo.dataset.webp;

        photo.addEventListener('click', () => {
            dialogImg.src = url;
            dialogWebpSource.srcset = webp;
            dialog.showModal();
        });
    }
}