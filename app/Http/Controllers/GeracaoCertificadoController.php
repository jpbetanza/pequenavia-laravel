<?php

namespace App\Http\Controllers;

use App\Exceptions\ImagemInvalidaException;
use App\Exceptions\TextoGrandeDemaisException;
use App\Http\Requests\GerarCertificadoRequest;
use App\Models\CampoCertificado;
use App\Models\ModeloCertificado;
use App\Services\CatalogoFontes;
use App\Services\GeradorCertificado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class GeracaoCertificadoController extends Controller
{
    /**
     * Mostra a página onde se preenchem os dados e se geram os certificados do modelo.
     */
    public function create(ModeloCertificado $modelo): InertiaResponse
    {
        Gate::authorize('gerar', $modelo);

        return Inertia::render('meus-certificados/gerar', [
            'modelo' => [
                'id' => $modelo->id,
                'title' => $modelo->title,
                'imagem_url' => route('modelos.imagem', $modelo),
            ],
            'campos' => $modelo->campos,
            'fontes' => CatalogoFontes::grupos(),
            'pesos_das_fontes' => CatalogoFontes::pesosPorPasta(),
        ]);
    }

    /**
     * Gera o PNG do certificado e registra a geração no histórico.
     */
    public function store(GerarCertificadoRequest $request, ModeloCertificado $modelo, GeradorCertificado $gerador): Response|JsonResponse
    {
        $campos = $modelo->campos;
        $valores = collect($request->validated('valores'))
            ->only($campos->pluck('nome')->all())
            ->map(fn (mixed $valor) => (string) $valor)
            ->all();

        try {
            $png = $gerador->gerar($this->lerFundo($modelo), $campos, $valores);
        } catch (ImagemInvalidaException|TextoGrandeDemaisException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $request->user()->geracoes()->create([
            'modelo_id' => $modelo->id,
            'modelo_title' => $modelo->title,
            'campos_valores' => $valores,
        ]);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => $this->cabecalhoDeDownload($modelo, $campos, $valores),
        ]);
    }

    /**
     * @throws ImagemInvalidaException
     */
    private function lerFundo(ModeloCertificado $modelo): string
    {
        $disco = Storage::disk('s3');

        if (! $disco->exists($modelo->image_path)) {
            throw new ImagemInvalidaException('Não foi possível ler a imagem do modelo.');
        }

        return $disco->get($modelo->image_path);
    }

    /**
     * "{título} - {primeiro valor}.png", sem caracteres de controle nem de caminho, com o nome em UTF-8
     * e uma alternativa ASCII para quem não lê `filename*`.
     *
     * @param  Collection<int, CampoCertificado>  $campos
     * @param  array<string, string>  $valores
     */
    private function cabecalhoDeDownload(ModeloCertificado $modelo, Collection $campos, array $valores): string
    {
        $primeiroValor = $campos
            ->map(fn (CampoCertificado $campo) => $valores[$campo->nome] ?? '')
            ->first(fn (string $valor) => $valor !== '');

        $nome = $this->nomeSeguro($primeiroValor === null ? $modelo->title : "{$modelo->title} - {$primeiroValor}").'.png';
        $alternativa = preg_replace('/[^A-Za-z0-9 ._()-]/', '_', Str::ascii($nome));

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nome, $alternativa);
    }

    private function nomeSeguro(string $nome): string
    {
        $semProibidos = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '', $nome) ?? '';

        return Str::limit(trim($semProibidos), 150, '');
    }
}
