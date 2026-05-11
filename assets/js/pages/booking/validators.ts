export function validateInput(input: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement): boolean {
	let valid = true;
	let selected = false;

	// Clear feedback error messages
	const feedback: HTMLElement = input.parentElement.querySelector('.invalid-feedback');
	if (feedback) {
		feedback.innerHTML = '';
	}

	if (input instanceof HTMLInputElement) {
		switch (input.type) {
			case 'email':
				valid = validateEmail(input.value);
				toggleInputError(valid, emailError);
				valid = valid && validateRequiredText(valid);
				return valid;
			case 'tel':
				valid = validatePhone(input.value);
				toggleInputError(valid, phoneError);
				valid = valid && validateRequiredText(valid);
				return valid;
			case 'text':
				return validateRequiredText(valid);
			case 'checkbox':
				if (!input.required) return true;
				const otherCheckboxes: NodeListOf<HTMLInputElement> = document.querySelectorAll(`input[type="checkbox"][name="${input.name}"]`);
				selected = false;
				otherCheckboxes.forEach(otherInput => {
					selected = selected || otherInput.checked;
				});
				if (!selected) {
					toggleInputError(false, input.dataset.requiredMessage ?? requiredField);
					return false;
				}
				return true;
			case 'radio':
				if (!input.required) return true;
				const otherRadios: NodeListOf<HTMLInputElement> = document.querySelectorAll(`input[type="radio"][name="${input.name}"]`);
				selected = false;
				otherRadios.forEach(otherInput => {
					selected = selected || otherInput.checked;
				});
				if (!selected) {
					toggleInputError(false, input.dataset.requiredMessage ?? requiredField);
					return false;
				}
				return true;
			case 'number':
				valid = !isNaN(parseInt(input.value));
				toggleInputError(valid, numberError);
				valid = valid && validateRequiredText(valid);
				if (valid && input.min && input.valueAsNumber < parseInt(input.min)) {
					valid = false;
					toggleInputError(valid, numberMinError.replace('%d', input.min));
				}
				if (valid && input.max && input.valueAsNumber > parseInt(input.max)) {
					valid = false;
					toggleInputError(valid, numberMaxError.replace('%d', input.max));
				}
				return valid;
		}
	}

	return validateRequiredText(valid);

	function validateRequiredText(valid: boolean): boolean {
		if (input.required && input.value.trim() === '') {
			valid = false;
		}
		toggleInputError(valid, input.dataset.requiredMessage ?? requiredField);
		return valid;
	}

	function toggleInputError(valid: boolean, message: string) {
		input.classList.add(valid ? 'is-valid' : 'is-invalid');
		input.classList.remove(valid ? 'is-invalid' : 'is-valid');
		if (!valid && message !== '') {
			if (feedback) {
				feedback.innerHTML += `<p>${message}</p>`;
			}
		}
	}
}

export function validateEmail(value: string): boolean {
	const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
	return re.test(value.toLowerCase().trim());
}

export function validatePhone(value: string): boolean {
	// Remove whitespaces
	value = value.replaceAll(' ', '');

	const isCZ = value.startsWith('+420') || value[0] !== '+';
	return isCZ ? /(?:\+\d{2,3})?\d{9}/.test(value) : /\+\d{2,3}\d{4,13}/.test(value);
}