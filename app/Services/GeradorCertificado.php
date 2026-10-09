<?php

namespace App\Services;

use App\Exceptions\ImagemInvalidaException;
use App\Exceptions\TextoGrandeDemaisException;
use App\Models\CampoCertificado;
use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;

/**
 * Desenha os campos de um modelo sobre a imagem de fundo e devolve o PNG.
 *
 * O texto é desenhado numa camada transparente ampliada (supersampling) e reduzida depois. Em escala 1×
 * o Imagick arredonda o avanço de cada letra para pixels inteiros; ampliado, a medida fica próxima da do
 * navegador e a posição guarda precisão de sub-pixel.
 *
 * A linha de base segue o editor, que posiciona o texto com `top: y` e `line-height: 1`: o conteúdo
 * (ascendente + descendente) fica centralizado numa caixa de altura igual ao tamanho da fonte.
 *
 * Limitação conhecida: o Imagick só aplica o kerning da tabela `kern`, não o da tabela GPOS que o
 * navegador aplica. Em fontes que dependem de GPOS o texto sai até ~3% mais largo; o centralizado
 * continua centralizado, porque o texto abre dos dois lados.
 */
class GeradorCertificado
{
    private const ESCALA = 8;

    private const LADO_MAXIMO_DA_CAMADA = 16384;

    private const AREA_MAXIMA_DA_CAMADA = 16_000_000;

    private const MEMORIA_MAXIMA = 1024 * 1024 * 1024;

    /**
     * @param  iterable<CampoCertificado>  $campos
     * @param  array<string, string>  $valores  texto de cada campo, indexado pelo nome do campo
     * @return string bytes do PNG, com as dimensões do fundo
     *
     * @throws ImagemInvalidaException
     * @throws TextoGrandeDemaisException
     */
    public function gerar(string $imagemDeFundo, iterable $campos, array $valores): string
    {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::MEMORIA_MAXIMA);

        $imagem = $this->abrir($imagemDeFundo);

        try {
            foreach ($campos as $campo) {
                $texto = trim((string) ($valores[$campo->nome] ?? ''));

                if ($texto !== '') {
                    $this->escrever($imagem, $campo, $texto);
                }
            }

            $imagem->setImageFormat('png');

            return $imagem->getImageBlob();
        } finally {
            $imagem->clear();
        }
    }

    /**
     * @throws ImagemInvalidaException
     */
    private function abrir(string $bytes): Imagick
    {
        try {
            $imagem = new Imagick;
            $imagem->readImageBlob($bytes);
            $imagem->setFirstIterator();
            // O navegador exibe a foto já girada; o editor posiciona os campos nessa orientação.
            $imagem->autoOrient();
            $imagem->setImagePage(0, 0, 0, 0);
            $imagem->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        } catch (ImagickException $e) {
            throw new ImagemInvalidaException('Não foi possível ler a imagem do modelo.', previous: $e);
        }

        return $imagem;
    }

    /**
     * @throws TextoGrandeDemaisException
     */
    private function escrever(Imagick $imagem, CampoCertificado $campo, string $texto): void
    {
        $arquivo = CatalogoFontes::arquivo($campo->fonte_familia, $campo->peso_fonte);
        $tamanho = $campo->tamanho_fonte;
        $espacamento = $campo->espacamento_letras;

        // Folga para floreios e ascendentes que passam da caixa de linha.
        $margemX = (int) ceil($tamanho * 0.5);
        $margemY = (int) ceil($tamanho * 0.75);

        for ($escala = self::ESCALA; $escala >= 1; $escala--) {
            $medidas = $this->medir($arquivo, $tamanho, $espacamento, $texto, $escala);
            $larguraDaCamada = (int) ceil($medidas['largura']) + 2 * $margemX + 1;
            $alturaDaCamada = (int) ceil($tamanho) + 2 * $margemY + 1;

            if (max($larguraDaCamada, $alturaDaCamada) * $escala <= self::LADO_MAXIMO_DA_CAMADA
                && $larguraDaCamada * $alturaDaCamada * $escala ** 2 <= self::AREA_MAXIMA_DA_CAMADA) {
                break;
            }
        }

        if ($escala < 1) {
            throw new TextoGrandeDemaisException("O texto do campo \"{$campo->nome}\" é grande demais para ser desenhado.");
        }

        $x = $campo->centralizado ? ($imagem->getImageWidth() - $medidas['largura']) / 2 : $campo->x;
        $y = $campo->y;

        $pincel = $this->pincel($arquivo, $tamanho, $espacamento, $escala);
        $pincel->setFillColor(new ImagickPixel($campo->cor));

        $camada = new Imagick;
        $camada->newImage($larguraDaCamada * $escala, $alturaDaCamada * $escala, new ImagickPixel('none'));
        $camada->annotateImage(
            $pincel,
            ($margemX + $x - floor($x)) * $escala,
            ($margemY + $y - floor($y) + $medidas['linhaDeBase']) * $escala,
            0,
            $texto,
        );

        if ($escala > 1) {
            $camada->resizeImage($larguraDaCamada, $alturaDaCamada, Imagick::FILTER_BOX, 1);
        }

        $imagem->compositeImage($camada, Imagick::COMPOSITE_OVER, (int) floor($x) - $margemX, (int) floor($y) - $margemY);
        $camada->clear();
    }

    /**
     * @return array{largura: float, linhaDeBase: float}
     */
    private function medir(string $arquivo, int $tamanho, float $espacamento, string $texto, int $escala): array
    {
        $sonda = new Imagick;
        $sonda->newImage(1, 1, new ImagickPixel('none'));
        $metricas = $sonda->queryFontMetrics($this->pincel($arquivo, $tamanho, $espacamento, $escala), $texto);
        $sonda->clear();

        return [
            'largura' => $metricas['textWidth'] / $escala,
            // O descendente vem negativo.
            'linhaDeBase' => ($tamanho + ($metricas['ascender'] + $metricas['descender']) / $escala) / 2,
        ];
    }

    private function pincel(string $arquivo, int $tamanho, float $espacamento, int $escala): ImagickDraw
    {
        $pincel = new ImagickDraw;
        $pincel->setFont($arquivo);
        $pincel->setFontSize($tamanho * $escala);
        $pincel->setTextKerning($espacamento * $escala);
        $pincel->setTextAntialias(true);

        return $pincel;
    }
}
