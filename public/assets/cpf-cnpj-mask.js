(function () {
    function mask(value) {
        var d = value.replace(/\D+/g, '').slice(0, 14);
        if (d.length <= 11) {
            return d
                .replace(/^(\d{3})(\d)/, '$1.$2')
                .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
        }
        return d
            .replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }

    document.querySelectorAll('input[data-mask="cpf-cnpj"]').forEach(function (input) {
        var apply = function () { input.value = mask(input.value); };
        input.addEventListener('input', apply);
        apply();
    });
})();
