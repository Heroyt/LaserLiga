import {startLoading, stopLoading} from "../../loaders";
import {initCopyToClipboard} from "../../functions";
import {fetchPost} from "../../api/client";
import {generateApiKey, invalidateApiKey} from "../../api/endpoints/admin";

declare global {
	const translations: { [index: string]: string }
}

export default function initArenaEdit() {
	const form = document.getElementById('arena-form') as HTMLFormElement;
    const idArena = parseInt(form.dataset.arena);
	const imgInput = document.getElementById('arena-image') as HTMLInputElement;
	const imgWrapper = document.getElementById('img') as HTMLDivElement;
	imgInput.addEventListener('change', () => {
		const file = imgInput.files[0];
		if (file) {
			const data = new FormData();
			data.append('action', 'upload');
			data.append('image', file);

			startLoading(true);
            fetchPost(
				form.action + '/image',
                data
			)
                .then(() => {
					stopLoading(true, true);
				})
				.catch(error => {
					console.error(error);
					stopLoading(false, true);
				});

			const fileReader = new FileReader();
			fileReader.readAsDataURL(file);
			fileReader.addEventListener("load", function () {
                imgWrapper.innerHTML = `<img alt="arena logo" src="${this.result}">`;
			});
		}
	});

	const addApiKeyBtn = document.getElementById('addApiKey') as HTMLButtonElement;
	const apiKeysWrapper = document.getElementById('api-keys') as HTMLDivElement;
	addApiKeyBtn.addEventListener('click', () => {
		startLoading(true);
        generateApiKey(idArena)
            .then(response => {
				stopLoading(true, true);
				const keyInput = document.createElement('div');
                const key = response.key;
                const id = response.id;
				keyInput.classList.add('input-group', 'mb-2');
				keyInput.dataset.id = id.toString();
				keyInput.innerHTML = `<input type="text" readonly="readonly" class="form-control col-9 text-center font-monospace bg-light-grey text-black" id="key-${id}" value="${key}">` +
					`<button type="button" data-action="copy-to-clipboard" data-target="#key-${id}" class="btn btn-secondary">` +
					`<i class="fa-solid fa-clipboard"></i>` +
					`</button>` +
					`<div class="form-floating">` +
                    `<input type="text" name="key[${id}][name]" class="form-control" id="key-${id}-name" placeholder="${translations.name}" required value="${response.name}">` +
					`<label for="key-${id}-name">${translations.name}</label>` +
					`</div>` +
					`<button type="button" class="delete btn btn-danger"><i class="fa-solid fa-trash"></i></button>`;
				apiKeysWrapper.appendChild(keyInput);
				initApiKey(keyInput);
				initCopyToClipboard(keyInput);
			})
			.catch(error => {
				console.error(error);
				stopLoading(false, true);
			})
	});

	(document.querySelectorAll('#api-keys .input-group') as NodeListOf<HTMLDivElement>).forEach(initApiKey);
}

function initApiKey(element: HTMLDivElement) {
	const deleteBtn = element.querySelector('.delete') as HTMLButtonElement;
	const id = parseInt(element.dataset.id);
	deleteBtn.addEventListener('click', () => {
		startLoading(true);
        invalidateApiKey(id)
			.then(() => {
				stopLoading(true, true);
				element.remove();
			})
			.catch(error => {
				console.error(error);
				stopLoading(false, true);
			})
	});
}