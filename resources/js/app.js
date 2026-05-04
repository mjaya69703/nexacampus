import './bootstrap';
import flatpickr from "flatpickr"; 
import 'flatpickr/dist/flatpickr.min.css';
import './../../vendor/power-components/livewire-powergrid/dist/powergrid'

window.previewImage = function (input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById(previewId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
};

window.initLivewireTomSelect = function ({
    selectId,
    property,
    placeholder = '',
    removeButtonTitle = 'Hapus item',
}) {
    const selectElement = document.getElementById(selectId);

    if (!selectElement || selectElement.tomselect || typeof window.TomSelect === 'undefined') {
        return;
    }

    const tomSelect = new window.TomSelect(selectElement, {
        plugins: {
            remove_button: {
                title: removeButtonTitle,
            },
        },
        persist: false,
        create: false,
        hidePlaceholder: true,
        placeholder,
    });

    tomSelect.on('change', () => {
        const selectedValues = tomSelect.getValue();
        const values = Array.isArray(selectedValues)
            ? selectedValues
            : (selectedValues ? selectedValues.split(',') : []);

        const componentElement = selectElement.closest('[wire\\:id]');
        if (!componentElement) {
            return;
        }

        const component = window.Livewire?.find(componentElement.getAttribute('wire:id'));
        if (!component) {
            return;
        }

        component.set(property, values);
    });
};
