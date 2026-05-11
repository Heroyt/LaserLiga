import Duplicator from "../../components/duplicator";

export default function initBookingSettings() {
    const form = document.querySelector<HTMLFormElement>('#booking-form');
    if (!form) {
        console.warn('Booking settings form not found.');
        return;
    }

    const arenaId = parseInt(form.dataset.arena ?? '0');

    initBookingTypes();

    function initBookingTypes() {
        const wrapper = document.querySelector<HTMLElement>('#booking-types');
        if (!wrapper) {
            return;
        }

        const addBtn = document.querySelector<HTMLButtonElement>('#add-type');
        if (!addBtn) {
            return;
        }

        // Initialize the duplicator
        const duplicator = new Duplicator(wrapper, addBtn, {
            template: document.querySelector<HTMLTemplateElement>('#type-template'),
            idReplace: '&id&',
            childSelector: '.booking-type',
            ajaxCreateUrl: form.dataset.createTypeUrl,
            ajaxCreateMethod: 'POST',
            ajaxCreateData: {name: 'Nový typ'},
            ajaxDeleteUrl: form.dataset.deleteTypeUrl,
            ajaxDeleteMethod: 'DELETE',
        });
    }
}