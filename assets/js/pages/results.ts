import {Modal} from 'bootstrap';
import axios, {AxiosResponse} from "axios";
import {getLink} from "../functions";

export default function initResults() {
	const leaderboardModalDom = document.getElementById('leaderboard-modal') as HTMLDivElement | null;
	if (!leaderboardModalDom) {
		return;
	}
	const leaderboardModalDomBody = leaderboardModalDom.querySelector('.modal-body') as HTMLDivElement;

	const leaderboardModal = new Modal(leaderboardModalDom);

	(document.querySelectorAll('.show-leaderboard') as NodeListOf<HTMLButtonElement>).forEach(btn => {
		const url = btn.dataset.href;
		btn.addEventListener('click', () => {
			axios.get(url)
				.then(response => {
					leaderboardModalDomBody.innerHTML = response.data;
					leaderboardModal.show();
				})
				.catch(response => {
					console.error(response.data);
					// TODO: Display error
				})
		});
	});

	// Auto-open tournament modal
	const modalDom = document.getElementById('tournament-modal') as HTMLDivElement;
	const modal = new Modal(modalDom);
	if (modalDom.dataset.show && modalDom.dataset.show === 'true') {
		modal.show();
	}

	initQuestionnaire();
}

function initQuestionnaire() {
	const modalDom = document.getElementById('questionnaire-modal') as HTMLDivElement;
	const questionModalDom = document.getElementById('questionnaire-question-modal') as HTMLDivElement;
	const previousBtn = questionModalDom.querySelector('.previous') as HTMLButtonElement;
	const nextBtn = questionModalDom.querySelector('.next') as HTMLButtonElement;
	const doneBtn = questionModalDom.querySelector('.done') as HTMLButtonElement;
	const closeBtn = questionModalDom.querySelector('.close') as HTMLButtonElement;
	const questionBody = questionModalDom.querySelector('.modal-body') as HTMLFormElement;

	let currentStep = -1;

	// Check DOM
	if (!modalDom || !questionModalDom || !previousBtn || !nextBtn || !questionBody || !doneBtn || !closeBtn) {
		return;
	}

	// Init modal objects
	const modal = new Modal(modalDom);
	const modalQuestion = new Modal(questionModalDom);
	// Auto-open modal
	if (modalDom.dataset.show && modalDom.dataset.show === 'true') {
		modal.show();
	}

	// Button functions
	(modalDom.querySelectorAll('.startQuestionnaire') as NodeListOf<HTMLButtonElement>).forEach(btn => {
		btn.addEventListener('click', () => {
			currentStep = -1;
			modal.hide();
			modalQuestion.show();
			axios.post(btn.dataset.href) // Select questionnaire
				.finally(() => {
					loadQuestion();
				})
		});
	});
	const showLaterBtn = modalDom.querySelector('#show-later') as HTMLButtonElement;
	const dontShowAgainBtn = modalDom.querySelector('#dont-show-again') as HTMLButtonElement;
	if (showLaterBtn) {
		showLaterBtn.addEventListener('click', () => {
			axios.post(getLink(['questionnaire', 'show_later']))
				.then(response => {
				})
				.catch(response => {
					console.error(response);
				});
		});
	}
	if (dontShowAgainBtn) {
		dontShowAgainBtn.addEventListener('click', () => {
			axios.post(getLink(['questionnaire', 'dont_show']))
				.then(response => {
				})
				.catch(response => {
					console.error(response);
				});
		});
	}
	nextBtn.addEventListener('click', () => {
		triggerSave();
		if (validateStep()) {
			loadQuestion();
		}
	});
	previousBtn.addEventListener('click', () => {
		triggerSave();
		currentStep -= 2;
		if (currentStep < 0) {
			currentStep = 0;
		}
		loadQuestion();
	});
	doneBtn.addEventListener('click', () => {
		if (validateStep()) {
			const data = new FormData(questionBody);
			axios.post(getLink(['questionnaire', 'done']), data)
				.then((response: AxiosResponse<{ step: number, html: string, total: number }>) => {
					currentStep = response.data.step;
					questionBody.innerHTML = response.data.html;
					if (currentStep <= 1) {
						previousBtn.ariaDisabled = 'true';
						previousBtn.disabled = true;
					} else {
						previousBtn.ariaDisabled = 'false';
						previousBtn.disabled = false;
					}
					if (currentStep === response.data.total) {
						nextBtn.ariaDisabled = 'true';
						nextBtn.disabled = true;
						nextBtn.classList.add('d-none');
						closeBtn.classList.add('d-none');
						doneBtn.classList.remove('d-none');
					} else if (currentStep > response.data.total) {
						nextBtn.classList.add('d-none');
						doneBtn.classList.add('d-none');
						closeBtn.classList.remove('d-none');
					} else {
						nextBtn.ariaDisabled = 'false';
						nextBtn.disabled = false;
						nextBtn.classList.remove('d-none');
						closeBtn.classList.add('d-none');
						doneBtn.classList.add('d-none');
					}
				})
				.catch(error => {
					console.log(error.response);
					questionBody.innerHTML = `<div class="alert alert-danger">${error.response.data.error}</div>`;
				});
		}
	})

	function triggerSave() {
		let event = new Event("autosave", {
			bubbles: true
		});
		questionBody.dispatchEvent(event);
	}

	function loadQuestion() {
		modal.hide();
		axios.get(getLink(['questionnaire', 'question', currentStep.toString()]))
			.then((response: AxiosResponse<{ step: number, html: string, total: number }>) => {
				currentStep = response.data.step;
				questionBody.innerHTML = response.data.html;
				questionBody.scrollTo(0, 0);
				if (currentStep <= 1) {
					previousBtn.ariaDisabled = 'true';
					previousBtn.disabled = true;
				} else {
					previousBtn.ariaDisabled = 'false';
					previousBtn.disabled = false;
				}
				if (currentStep === response.data.total) {
					nextBtn.ariaDisabled = 'true';
					nextBtn.disabled = true;
					nextBtn.classList.add('d-none');
					closeBtn.classList.add('d-none');
					doneBtn.classList.remove('d-none');
				} else if (currentStep > response.data.total) {
					nextBtn.classList.add('d-none');
					doneBtn.classList.add('d-none');
					closeBtn.classList.remove('d-none');
				} else {
					nextBtn.ariaDisabled = 'false';
					nextBtn.disabled = false;
					nextBtn.classList.remove('d-none');
					closeBtn.classList.add('d-none');
					doneBtn.classList.add('d-none');
				}
			})
			.catch(error => {
				console.log(error.response);
				questionBody.innerHTML = `<div class="alert alert-danger">${error.response.data.error}</div>`;
			});
	}

	function validateStep() {
		let success = true;
		questionBody.querySelectorAll('input').forEach(input => {
			if (input.classList.contains('not-required')) {
				return;
			}
			let valid;
			if (input.classList.contains('form-check-input')) {
				valid = questionBody.querySelectorAll(`[name="${input.name}"]:checked`).length > 0;
			} else {
				valid = input.value.trim() !== '';
			}
			if (valid) {
				input.classList.remove('is-invalid');
			} else {
				input.classList.add('is-invalid');
				success = false;
			}
		});
		return success;
	}
}