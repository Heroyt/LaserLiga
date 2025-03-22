import {startLoading, stopLoading} from "../../loaders";
import {assignGamePhotos, deletePhoto, deletePhotos, sendPhotosMail, setPhotosSecret} from "../../api/endpoints/admin";
import {triggerNotification, triggerNotificationError} from "../../components/notifications";
import {initNativeDialog} from "../../components/dialog";

declare global {
    const arenaId : number;
}

export default function initPhotos() {
    const dateInput = document.getElementById("selected-date") as HTMLInputElement;
    const filterPhotosInput = document.getElementById("filter-photos") as HTMLInputElement;
    dateInput.addEventListener("change", () => {
        window.location.href = `?date=${dateInput.value}&filterphotos=${filterPhotosInput.checked ? '1' : '0'}`;
    });
    filterPhotosInput.addEventListener("change", () => {
        window.location.href = `?date=${dateInput.value}&filterphotos=${filterPhotosInput.checked ? '1' : '0'}`;
    })

    const dialog = document.getElementById('photo-dialog') as HTMLDialogElement;
    dialog.addEventListener('click', () => {
        dialog.close();
    });

    const mailDialog = document.getElementById('send-mail-dialog') as HTMLDialogElement;
    initNativeDialog(mailDialog);
    const mailsForm = mailDialog.querySelector<HTMLFormElement>('#mails-form');
    const mailInput = mailDialog.querySelector<HTMLTextAreaElement>('#mails');
    const sendMailBtn = mailDialog.querySelector<HTMLButtonElement>('#send-mail');
    let selectedGame = '';

    const dialogImg = dialog.querySelector('img');
    const dialogWebpSource = dialog.querySelector<HTMLSourceElement>('.webp-source');

    const gameGroups = document.querySelectorAll<HTMLDivElement>('.game-group');

    const selectedPhotos = new Set<HTMLElement>();
    const photos = document.querySelectorAll<HTMLElement>(".unassigned-photo");
    for (const photo of photos) {
        const input = photo.querySelector<HTMLInputElement>(".photo-select");
        const picture = photo.querySelector('picture');
        const deleteBtn = photo.querySelector<HTMLButtonElement>('.delete');

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
    }

    for (const gameGroup of gameGroups) {
        const assignPhotosBtn = gameGroup.querySelector<HTMLButtonElement>('.assign-photos');
        const sendEmailBtn = gameGroup.querySelector<HTMLButtonElement>('.send-email');
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
    }

    mailsForm.addEventListener('submit', e => {
       e.preventDefault();
       sendEmails();
    });

    mailInput.addEventListener('input', e => {
        validateEmails();
    });

    const deleteBulkBtn = document.getElementById('delete-bulk') as HTMLButtonElement;
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