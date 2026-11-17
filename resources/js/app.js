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

// Menu hambúrguer (painel, admin e página inicial). Estado e regras ficam aqui; a marcação só liga propriedades.
Alpine.data('navMenu', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
    get closed() {
        return !this.open;
    },
    get expanded() {
        return this.open ? 'true' : 'false';
    },
    get toggleLabel() {
        return this.open ? this.$root.dataset.closeLabel : this.$root.dataset.openLabel;
    },
}));

// Bloco recolhível (filtros do admin no celular).
Alpine.data('disclosure', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    get expanded() {
        return this.open ? 'true' : 'false';
    },
}));

// Campo somente leitura com botão "Copiar".
Alpine.data('copyField', () => ({
    done: false,
    timer: null,
    async copy() {
        const input = this.$refs.field;
        try {
            await navigator.clipboard.writeText(input.value);
        } catch {
            input.select();
            document.execCommand('copy');
        }
        this.done = true;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            this.done = false;
        }, 2000);
    },
    select() {
        this.$refs.field.select();
    },
    get label() {
        return this.done ? this.$root.dataset.copied : this.$root.dataset.copy;
    },
    get announce() {
        return this.done ? this.$root.dataset.copied : '';
    },
}));

window.Alpine = Alpine;
Alpine.start();
