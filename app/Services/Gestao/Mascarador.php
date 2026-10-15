<?php

namespace App\Services\Gestao;

use Normalizer;

/**
 * Troca dados pessoais por marcadores antes de o texto sair do app (JEV e Haiku).
 */
class Mascarador
{
    private const EMAIL = '/[\p{L}\p{N}._%+-]+@[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)*\.\p{L}{2,}/u';

    private const DOCUMENTO = '/(?<![\d.])(?:\d{3}\.\d{3}\.\d{3}-\d{2}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{14})(?!\d)/';

    private const TELEFONE = '/(?<!\d)(?:\+55\s?)?(?:\(?\d{2}\)?\s?)?(?:9\d{4}|\d{4})[\s-]?\d{4}(?!\d)/';

    /** Letra sem acento => variantes acentuadas que o nome pode ter no texto. */
    private const VARIANTES = [
        'a' => '[aàáâãä]',
        'e' => '[eèéêë]',
        'i' => '[iìíîï]',
        'o' => '[oòóôõö]',
        'u' => '[uùúûü]',
        'c' => '[cç]',
        'n' => '[nñ]',
    ];

    /**
     * @param  list<string>  $nomes
     */
    public function mascarar(string $texto, array $nomes = []): string
    {
        $texto = $this->trocar(self::EMAIL, '[e-mail]', $texto);
        $texto = $this->trocar(self::DOCUMENTO, '[documento]', $texto);
        $texto = $this->trocar(self::TELEFONE, '[telefone]', $texto);

        foreach ($this->palavras($nomes) as $palavra) {
            $texto = $this->trocar($this->padraoDoNome($palavra), '[nome]', $texto);
        }

        return $texto;
    }

    private function trocar(string $padrao, string $marcador, string $texto): string
    {
        return preg_replace($padrao, $marcador, $texto) ?? $texto;
    }

    /**
     * Cada parte do nome com 3 letras ou mais, para que "Maria José" também tire "Maria" e "José" sozinhos.
     *
     * @param  list<string>  $nomes
     * @return list<string>
     */
    private function palavras(array $nomes): array
    {
        $palavras = [];

        foreach ($nomes as $nome) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', $nome, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palavra) {
                if (mb_strlen($palavra) >= 3) {
                    $palavras[$palavra] = $palavra;
                }
            }
        }

        return array_values($palavras);
    }

    /** Palavra inteira, sem diferenciar maiúsculas nem acentos. */
    private function padraoDoNome(string $palavra): string
    {
        $semAcento = preg_replace('/\p{Mn}/u', '', Normalizer::normalize($palavra, Normalizer::FORM_D) ?: $palavra) ?? $palavra;

        $padrao = '';

        foreach (mb_str_split($semAcento) as $letra) {
            $padrao .= self::VARIANTES[mb_strtolower($letra)] ?? preg_quote($letra, '/');
        }

        return '/(?<![\p{L}\p{N}])'.$padrao.'(?![\p{L}\p{N}])/iu';
    }
}
