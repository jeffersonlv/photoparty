import './bootstrap';
// Build CSP do Alpine: não usa eval/new Function, compatível com "script-src 'self'".
// Por isso a lógica fica em Alpine.data() e o HTML só referencia propriedades/métodos.
import Alpine from '@alpinejs/csp';

Alpine.data('passwordToggle', () => ({
    show: false,
    toggle() {
        this.show = !this.show;
    },
    get inputType() {
        return this.show ? 'text' : 'password';
    },
    get label() {
        return this.show ? this.$root.dataset.hide : this.$root.dataset.show;
    },
}));

Alpine.data('confirmSubmit', () => ({
    ask(event) {
        if (!window.confirm(this.$root.dataset.confirm)) {
            event.preventDefault();
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
