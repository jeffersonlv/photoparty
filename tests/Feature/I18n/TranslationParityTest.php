<?php

/** Todas as chaves de tradução existem em pt_BR e en (evita texto em português na versão em inglês). */
function flatKeys(array $items, string $prefix = ''): array
{
    $keys = [];
    foreach ($items as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        $keys = is_array($value) && ! array_is_list($value)
            ? array_merge($keys, flatKeys($value, $full))
            : array_merge($keys, [$full]);
    }

    return $keys;
}

it('has the same translation keys in pt_BR and en', function () {
    foreach (glob(lang_path('pt_BR/*.php')) as $file) {
        $name = basename($file);
        $enFile = lang_path('en/'.$name);

        // Estes três vêm em inglês do próprio Laravel (vendor); só o pt_BR fica no projeto.
        if (in_array($name, ['auth.php', 'pagination.php', 'validation.php'], true) && ! file_exists($enFile)) {
            expect(trans('auth.failed', [], 'en'))->not->toBe('auth.failed');
            continue;
        }

        expect(file_exists($enFile))->toBeTrue("lang/en/{$name} está faltando");

        $pt = flatKeys(require $file);
        $en = flatKeys(require $enFile);

        expect(array_values(array_diff($pt, $en)))->toBe([], "chaves sem inglês em {$name}");
        expect(array_values(array_diff($en, $pt)))->toBe([], "chaves sem português em {$name}");
    }
});
