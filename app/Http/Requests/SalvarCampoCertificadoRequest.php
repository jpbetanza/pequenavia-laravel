<?php

namespace App\Http\Requests;

use App\Services\CatalogoFontes;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Valida a criação e a atualização de um campo. Todos os atributos são opcionais:
 * o editor salva um atributo por vez e o campo novo usa os padrões do banco.
 */
class SalvarCampoCertificadoRequest extends FormRequest
{
    /**
     * A autorização vem antes da validação: quem não vê o modelo recebe 404 mesmo com dados inválidos.
     */
    public function authorize(): Response
    {
        return Gate::inspect('editarCampos', $this->route('modelo'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('campos_certificado', 'nome')
                    ->where('modelo_id', $this->route('modelo')->id)
                    ->ignore($this->route('campo')?->id),
            ],
            'label' => ['sometimes', 'nullable', 'string', 'max:150'],
            'x' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'y' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'cor' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tamanho_fonte' => ['sometimes', 'integer', 'min:4', 'max:1000'],
            'fonte_familia' => ['sometimes', Rule::in(CatalogoFontes::familias())],
            'peso_fonte' => ['sometimes', 'integer', Rule::in(CatalogoFontes::PESOS)],
            'espacamento_letras' => ['sometimes', 'numeric', 'min:-50', 'max:200'],
            'centralizado' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do campo.',
            'nome.unique' => 'Já existe um campo com este nome neste modelo.',
            'x.min' => 'A posição não pode ser negativa.',
            'y.min' => 'A posição não pode ser negativa.',
            'cor.regex' => 'Informe a cor no formato #RRGGBB.',
            'fonte_familia.in' => 'Escolha uma fonte da lista.',
            'peso_fonte.in' => 'Escolha um peso de fonte da lista.',
        ];
    }
}
