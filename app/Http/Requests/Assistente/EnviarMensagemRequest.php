<?php

namespace App\Http\Requests\Assistente;

use App\Services\Assistente\AnexoRecebido;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Mensagem enviada ao assistente, com ou sem anexos. Conversa de outro usuário responde 404 antes de qualquer validação.
 *
 * Planilhas e arquivos de texto chegam já convertidos pelo navegador em `conteudo`; imagem e PDF chegam em `arquivo`.
 */
class EnviarMensagemRequest extends FormRequest
{
    public const MIMES_DE_IMAGEM = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    public function authorize(): Response
    {
        return Gate::inspect('enviar', $this->route('conversa'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'texto' => ['nullable', 'required_without:anexos', 'string', 'max:'.config('assistente.max_caracteres_mensagem')],
            'anexos' => ['sometimes', 'array', 'max:'.config('assistente.max_anexos')],
            'anexos.*' => ['array:nome,tipo,conteudo,truncado,arquivo'],
            'anexos.*.nome' => ['required', 'string', 'max:255'],
            'anexos.*.tipo' => ['required', 'string', 'in:planilha,texto,imagem,pdf'],
            'anexos.*.conteudo' => ['required_if:anexos.*.tipo,planilha,texto', 'prohibited_if:anexos.*.tipo,imagem,pdf', 'nullable', 'string', 'max:'.config('assistente.max_caracteres_anexo')],
            'anexos.*.truncado' => ['sometimes', 'boolean'],
            'anexos.*.arquivo' => ['required_if:anexos.*.tipo,imagem,pdf', 'prohibited_if:anexos.*.tipo,planilha,texto', 'nullable', 'file', 'max:'.config('assistente.max_kb_arquivo')],
        ];
    }

    /**
     * O tipo declarado precisa bater com o arquivo: imagem só PNG, JPEG, WebP ou GIF; PDF só PDF.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('anexos', []) as $indice => $anexo) {
                    $arquivo = $this->file("anexos.{$indice}.arquivo");
                    $tipo = is_array($anexo) ? ($anexo['tipo'] ?? null) : null;

                    if (! $arquivo instanceof UploadedFile || ! in_array($tipo, ['imagem', 'pdf'], true)) {
                        continue;
                    }

                    $aceitos = $tipo === 'imagem' ? self::MIMES_DE_IMAGEM : ['application/pdf'];

                    if (! in_array($arquivo->getMimeType(), $aceitos, true)) {
                        $validator->errors()->add("anexos.{$indice}.arquivo", $tipo === 'imagem'
                            ? 'A imagem precisa ser PNG, JPEG, WebP ou GIF.'
                            : 'O arquivo precisa ser um PDF.');
                    }
                }
            },
        ];
    }

    /**
     * Anexos validados, na ordem em que foram enviados.
     *
     * @return list<AnexoRecebido>
     */
    public function anexos(): array
    {
        /** @var array<int, array{nome: string, tipo: 'planilha'|'texto'|'imagem'|'pdf', conteudo?: string|null, truncado?: bool|string|null}> $anexos */
        $anexos = array_values((array) $this->validated('anexos', []));

        return array_map(fn (array $anexo, int $indice) => new AnexoRecebido(
            nome: $anexo['nome'],
            tipo: $anexo['tipo'],
            conteudo: $anexo['conteudo'] ?? null,
            truncado: filter_var($anexo['truncado'] ?? false, FILTER_VALIDATE_BOOLEAN),
            arquivo: $this->file("anexos.{$indice}.arquivo"),
        ), $anexos, array_keys($anexos));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.required_without' => 'Escreva uma mensagem ou anexe um arquivo.',
            'texto.string' => 'A mensagem precisa ser um texto.',
            'texto.max' => 'A mensagem pode ter até :max caracteres.',
            'anexos.max' => 'Envie até :max arquivos por mensagem.',
            'anexos.*.nome.required' => 'Falta o nome do arquivo.',
            'anexos.*.tipo.in' => 'Esse tipo de arquivo não é aceito.',
            'anexos.*.conteudo.required_if' => 'Não consegui ler o conteúdo do arquivo.',
            'anexos.*.conteudo.max' => 'O conteúdo do arquivo passou de :max caracteres.',
            'anexos.*.arquivo.required_if' => 'Falta o arquivo.',
            'anexos.*.arquivo.file' => 'O arquivo não chegou inteiro. Tente de novo.',
            'anexos.*.arquivo.max' => 'Cada arquivo pode ter até 5 MB.',
        ];
    }
}
