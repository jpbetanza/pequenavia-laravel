<?php

namespace App\Http\Requests\Agendamentos;

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Pedido de reserva do formulário público, feito por quem não tem login.
 *
 * Ordem: slug inexistente responde 404; o honeypot preenchido finge sucesso sem validar nem gravar; só então valida.
 * Espaço, grupo e equipamentos precisam ser do dono da agenda. Espaço precisa estar ativo e aceitar solicitações.
 */
class StoreSolicitacaoPublicaRequest extends FormRequest
{
    private ?User $dono = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dono da agenda do slug da rota. Slug que não existe responde 404.
     */
    public function dono(): User
    {
        return $this->dono ??= User::query()->where('agenda_slug', $this->route('slug'))->firstOrFail();
    }

    /**
     * @throws HttpResponseException
     */
    public function validateResolved(): void
    {
        $this->dono();

        if ($this->filled('site')) {
            throw new HttpResponseException(redirect()->route('reservar.enviado', ['slug' => $this->route('slug')]));
        }

        parent::validateResolved();
    }

    /**
     * O telefone pode vir com máscara: guarda só os dígitos.
     */
    protected function prepareForValidation(): void
    {
        $telefone = $this->input('telefone');

        if (is_string($telefone)) {
            $this->merge(['telefone' => preg_replace('/\D+/', '', $telefone)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dono = $this->dono();
        $fuso = config('agendamentos.fuso');
        $hoje = CarbonImmutable::now($fuso)->toDateString();

        return [
            'grupo_id' => [
                'required',
                'uuid',
                Rule::exists('grupos', 'id')->where('user_id', $dono->id)->where('ativo', true),
            ],
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['required', 'digits_between:10,13'],
            'titulo' => ['required', 'string', 'max:160'],
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$hoje],
            'inicio' => ['required', 'date_format:H:i,H:i:s'],
            'fim' => ['required', 'date_format:H:i,H:i:s'],
            'espaco_id' => [
                'nullable',
                'uuid',
                Rule::exists('espacos', 'id')
                    ->where('user_id', $dono->id)
                    ->where('ativo', true)
                    ->where('aceita_solicitacoes', true),
            ],
            'pessoas' => ['nullable', 'integer', 'min:1'],
            'equipamentos' => ['nullable', 'array'],
            'equipamentos.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('equipamentos', 'id')->where('user_id', $dono->id),
            ],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Regras que dependem de outros campos: fim depois do início, antecedência e duração do espaço, e manutenção.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $this->input('data');
            $inicio = $this->input('inicio');
            $fim = $this->input('fim');

            if ($validator->errors()->hasAny(['data', 'inicio', 'fim']) || ! is_string($data) || ! is_string($inicio) || ! is_string($fim)) {
                return;
            }

            $fuso = config('agendamentos.fuso');
            $inicioDaReserva = CarbonImmutable::parse("{$data} {$inicio}", $fuso);
            $fimDaReserva = CarbonImmutable::parse("{$data} {$fim}", $fuso);

            if ($fimDaReserva->lessThanOrEqualTo($inicioDaReserva)) {
                $validator->errors()->add('fim', 'O horário final precisa ser depois do horário inicial.');

                return;
            }

            $this->conferirEspaco($validator, $inicioDaReserva, $fimDaReserva);
            $this->conferirEquipamentos($validator, $data);
        });
    }

    /**
     * Antecedência mínima e duração máxima do espaço escolhido. Sem espaço ("qualquer espaço"), nada a conferir aqui.
     */
    private function conferirEspaco(Validator $validator, CarbonImmutable $inicioDaReserva, CarbonImmutable $fimDaReserva): void
    {
        $espacoId = $this->input('espaco_id');

        if (! is_string($espacoId) || $validator->errors()->has('espaco_id')) {
            return;
        }

        $espaco = Espaco::query()->findOrFail($espacoId);

        if ($espaco->antecedencia_min_horas > 0
            && $inicioDaReserva->lessThan(CarbonImmutable::now(config('agendamentos.fuso'))->addHours($espaco->antecedencia_min_horas))) {
            $validator->errors()->add('inicio', "Este espaço precisa de pelo menos {$espaco->antecedencia_min_horas} hora(s) de antecedência.");
        }

        $duracaoEmMinutos = intdiv($fimDaReserva->getTimestamp() - $inicioDaReserva->getTimestamp(), 60);

        if ($espaco->duracao_max_min !== null && $duracaoEmMinutos > $espaco->duracao_max_min) {
            $validator->errors()->add('fim', "Este espaço aceita no máximo {$espaco->duracao_max_min} minutos por pedido.");
        }
    }

    /**
     * Equipamento em manutenção na data pedida não pode ser solicitado.
     */
    private function conferirEquipamentos(Validator $validator, string $data): void
    {
        $equipamentos = $this->input('equipamentos', []);

        if (! is_array($equipamentos) || $equipamentos === [] || $this->temErroEm($validator, 'equipamentos')) {
            return;
        }

        $emManutencao = Equipamento::query()
            ->whereIn('id', $equipamentos)
            ->whereDate('manutencao_ate', '>=', $data)
            ->exists();

        if ($emManutencao) {
            $validator->errors()->add('equipamentos', 'Um dos equipamentos está em manutenção nesta data.');
        }
    }

    /**
     * Há erro em `campo` ou em algum item dele (`campo.0`, `campo.1`…)?
     */
    private function temErroEm(Validator $validator, string $campo): bool
    {
        return collect($validator->errors()->keys())
            ->contains(fn (string $chave): bool => $chave === $campo || str_starts_with($chave, $campo.'.'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grupo_id.required' => 'Escolha a pastoral ou grupo.',
            'grupo_id.uuid' => 'A pastoral ou grupo escolhido é inválido.',
            'grupo_id.exists' => 'A pastoral ou grupo escolhido não está disponível.',
            'nome.required' => 'Informe o seu nome.',
            'nome.max' => 'O nome pode ter no máximo 120 caracteres.',
            'telefone.required' => 'Informe o telefone com DDD.',
            'telefone.digits_between' => 'O telefone deve ter entre 10 e 13 dígitos, com DDD.',
            'titulo.required' => 'Informe a atividade.',
            'titulo.max' => 'A atividade pode ter no máximo 160 caracteres.',
            'data.required' => 'Informe a data.',
            'data.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
            'data.after_or_equal' => 'A data não pode ser anterior a hoje.',
            'inicio.required' => 'Informe o horário de início.',
            'inicio.date_format' => 'O horário de início deve estar no formato HH:MM.',
            'fim.required' => 'Informe o horário de fim.',
            'fim.date_format' => 'O horário de fim deve estar no formato HH:MM.',
            'espaco_id.uuid' => 'O espaço escolhido é inválido.',
            'espaco_id.exists' => 'O espaço escolhido não aceita solicitações.',
            'pessoas.integer' => 'O número de pessoas deve ser um número inteiro.',
            'pessoas.min' => 'O número de pessoas deve ser de pelo menos 1.',
            'equipamentos.array' => 'Os equipamentos devem ser uma lista.',
            'equipamentos.*.required' => 'Um dos equipamentos está vazio.',
            'equipamentos.*.uuid' => 'Um dos equipamentos é inválido.',
            'equipamentos.*.distinct' => 'Um equipamento aparece mais de uma vez.',
            'equipamentos.*.exists' => 'Um dos equipamentos não está disponível.',
            'observacoes.max' => 'As observações podem ter no máximo 1000 caracteres.',
        ];
    }
}
