import './bootstrap';
import flatpickr from "flatpickr"; 
import 'flatpickr/dist/flatpickr.min.css';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import './../../vendor/power-components/livewire-powergrid/dist/powergrid'

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

window.L = L;

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
