<?php

namespace App\Services\Assistente;

use App\Models\Conversa;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;

/**
 * Anexos das mensagens do usuário. Texto (planilha convertida, .txt) fica na própria mensagem; imagem e PDF ficam
 * no bucket, numa pasta por conversa, e vão ao modelo como data URL sempre que a mensagem entra no contexto.
 *
 * O formato gravado em `mensagens.anexos` é uma lista de
 * `{id, nome, tipo, tamanho, caminho?, mime?, texto?, truncado?}`.
 */
class Anexos
{
    private const EXTENSOES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    /** @var array<string, string|null> conteúdo em base64 já lido do bucket, por caminho (uma pergunta lê várias vezes) */
    private array $lidos = [];

    public static function pastaDa(string $conversaId): string
    {
        return "assistente/{$conversaId}";
    }

    /**
     * Grava os arquivos no bucket e devolve o que vai para `mensagens.anexos`.
     *
     * @param  list<AnexoRecebido>  $recebidos
     * @return list<array<string, mixed>>
     */
    public function guardar(Conversa $conversa, array $recebidos): array
    {
        return array_map(function (AnexoRecebido $anexo) use ($conversa): array {
            $id = (string) Str::uuid();

            if ($anexo->arquivo === null) {
                $texto = (string) $anexo->conteudo;

                return [
                    'id' => $id,
                    'nome' => $anexo->nome,
                    'tipo' => $anexo->tipo,
                    'tamanho' => strlen($texto),
                    'texto' => $texto,
                    'truncado' => $anexo->truncado,
                ];
            }

            $mime = (string) $anexo->arquivo->getMimeType();
            $nomeNoBucket = $id.'.'.(self::EXTENSOES[$mime] ?? 'bin');
            $caminho = Storage::disk('s3')->putFileAs(self::pastaDa($conversa->id), $anexo->arquivo, $nomeNoBucket);

            return [
                'id' => $id,
                'nome' => $anexo->nome,
                'tipo' => $anexo->tipo,
                'tamanho' => (int) $anexo->arquivo->getSize(),
                'caminho' => $caminho === false ? null : $caminho,
                'mime' => $mime,
            ];
        }, $recebidos);
    }

    /**
     * Conteúdo da mensagem do usuário para o modelo: o texto puro quando não há anexos; senão, uma lista de partes
     * (formato OpenAI/OpenRouter): o texto digitado, cada anexo de texto num bloco `<anexo>` e as imagens e PDFs.
     *
     * @param  list<array<string, mixed>>  $anexos
     * @return string|list<array<string, mixed>>
     */
    public function conteudoParaOModelo(?string $texto, array $anexos): string|array
    {
        $texto = trim((string) $texto);

        if ($anexos === []) {
            return $texto;
        }

        $partes = [['type' => 'text', 'text' => $texto === '' ? '(Mensagem sem texto, só com anexos.)' : $texto]];

        foreach ($anexos as $anexo) {
            $partes = [...$partes, ...$this->partesDe($anexo)];
        }

        return $partes;
    }

    /**
     * O que o painel mostra de cada anexo: nada do conteúdo.
     *
     * @param  list<array<string, mixed>>|null  $anexos
     * @return list<array{id: string, nome: string, tipo: string, tamanho: int, truncado: bool}>
     */
    public function resumo(?array $anexos): array
    {
        return array_map(fn (array $anexo) => [
            'id' => (string) $anexo['id'],
            'nome' => (string) $anexo['nome'],
            'tipo' => (string) $anexo['tipo'],
            'tamanho' => (int) $anexo['tamanho'],
            'truncado' => (bool) ($anexo['truncado'] ?? false),
        ], $anexos ?? []);
    }

    /**
     * @param  array<string, mixed>  $anexo
     * @return list<array<string, mixed>>
     */
    private function partesDe(array $anexo): array
    {
        $nome = htmlspecialchars((string) $anexo['nome'], ENT_QUOTES);
        $tipo = (string) $anexo['tipo'];

        if (in_array($tipo, ['planilha', 'texto'], true)) {
            $aviso = ($anexo['truncado'] ?? false) ? ' truncado="sim"' : '';

            return [['type' => 'text', 'text' => "<anexo nome=\"{$nome}\" tipo=\"{$tipo}\"{$aviso}>\n{$anexo['texto']}\n</anexo>"]];
        }

        $base64 = $this->base64(isset($anexo['caminho']) ? (string) $anexo['caminho'] : null);

        if ($base64 === null) {
            return [['type' => 'text', 'text' => "<anexo nome=\"{$nome}\" tipo=\"{$tipo}\" indisponivel=\"sim\"/>"]];
        }

        $dataUrl = 'data:'.$anexo['mime'].';base64,'.$base64;
        $rotulo = ['type' => 'text', 'text' => "<anexo nome=\"{$nome}\" tipo=\"{$tipo}\"/>"];

        return $tipo === 'imagem'
            ? [$rotulo, ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]]]
            : [$rotulo, ['type' => 'file', 'file' => ['filename' => (string) $anexo['nome'], 'file_data' => $dataUrl]]];
    }

    private function base64(?string $caminho): ?string
    {
        if ($caminho === null) {
            return null;
        }

        if (! array_key_exists($caminho, $this->lidos)) {
            try {
                $conteudo = Storage::disk('s3')->get($caminho);
            } catch (UnableToReadFile) {
                // Arquivo apagado do bucket: o modelo é avisado de que o anexo não está mais disponível.
                $conteudo = null;
            }

            $this->lidos[$caminho] = $conteudo === null ? null : base64_encode($conteudo);
        }

        return $this->lidos[$caminho];
    }
}
