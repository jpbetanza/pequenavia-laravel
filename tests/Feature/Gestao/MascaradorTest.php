<?php

use App\Services\Gestao\Mascarador;

test('troca e-mails, telefones e documentos pelos marcadores', function (string $original, string $esperado) {
    expect((new Mascarador)->mascarar($original))->toBe($esperado);
})->with([
    'e-mail' => ['Escreva para ana.lima@paroquia.org.br hoje', 'Escreva para [e-mail] hoje'],
    'celular com DDD' => ['Ligue (11) 98888-7777 depois', 'Ligue [telefone] depois'],
    'celular sem pontuação' => ['Meu número é 11988887777', 'Meu número é [telefone]'],
    'celular com +55' => ['WhatsApp +55 11 98888-7777', 'WhatsApp [telefone]'],
    'fixo sem DDD' => ['Secretaria 3333-4444', 'Secretaria [telefone]'],
    'CPF' => ['CPF 123.456.789-09 do padre', 'CPF [documento] do padre'],
    'CNPJ' => ['CNPJ 12.345.678/0001-90 da paróquia', 'CNPJ [documento] da paróquia'],
]);

test('troca os nomes informados por [nome], como palavra inteira e sem diferenciar maiúsculas nem acentos', function () {
    $mascarado = (new Mascarador)->mascarar(
        'MARIA JOSÉ pediu. O joao também. Mariana não muda, nem Ed.',
        ['Maria José', 'João'],
    );

    expect($mascarado)->toBe('[nome] [nome] pediu. O [nome] também. Mariana não muda, nem Ed.');
});

test('nomes com menos de 3 letras não são trocados', function () {
    expect((new Mascarador)->mascarar('Ed e Ana vieram', ['Ed', 'Ana']))->toBe('Ed e [nome] vieram');
});

test('texto sem dados pessoais volta igual', function () {
    expect((new Mascarador)->mascarar('Reserva do salão para 15/10 às 10:30, 2 cadeiras.'))
        ->toBe('Reserva do salão para 15/10 às 10:30, 2 cadeiras.');
});
