import {Modal} from 'bootstrap'

const smallLoader = document.getElementById("smallLoader");
const loadingModalDom = document.getElementById("loader-modal");
const loadingModal = new Modal(loadingModalDom, {backdrop: "static"});
let loadingCounter = 0;

export function startLoading(small = false) {
	loadingCounter++;
	if (small) {
		smallLoader.classList.remove("d-none");
		smallLoader.querySelector('.loader').classList.remove("d-none");
	} else {
		loadingModal.show();
		loadingModalDom.querySelector('.loader').classList.remove("d-none");
	}
}

export function stopLoading(success = true, small = false) {
	loadingCounter--;
	if (loadingCounter > 0) {
		return;
	}
	const marker = (small ? smallLoader : loadingModalDom).querySelector(success ? ".successAnimation" : ".errorAnimation");

	if (small) {
		smallLoader.querySelector('.loader').classList.add("d-none");
	} else {
		loadingModalDom.querySelector('.loader').classList.add("d-none");
	}
	marker.classList.add("animated");
	setTimeout(() => {
		if (small) {
			smallLoader.classList.add("d-none");
		} else {
			loadingModal.hide();
		}
		marker.classList.remove("animated");
	}, 1200);
}
