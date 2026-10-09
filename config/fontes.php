<?php

/*
| Catálogo de fontes dos campos do certificado.
|
| Cada família exibida no editor aponta para uma pasta de arquivos .ttf (um por peso) dentro de
| `diretorio`. As fontes de "Sistema" usam equivalentes livres com as mesmas medidas, porque as
| originais da Microsoft não existem no servidor; `google` diz de qual família do Google Fonts
| cada uma é baixada. As demais são baixadas pelo próprio nome.
*/

return [
    'diretorio' => resource_path('fonts'),

    'grupos' => [
        'Sistema' => [
            'Georgia' => 'gelasio',
            'Arial' => 'arimo',
            'Times New Roman' => 'tinos',
            'Courier New' => 'cousine',
        ],
        'Serif' => [
            'Cormorant Garamond' => 'cormorant-garamond',
            'EB Garamond' => 'eb-garamond',
            'Lora' => 'lora',
            'Merriweather' => 'merriweather',
            'Playfair Display' => 'playfair-display',
            'Libre Baskerville' => 'libre-baskerville',
        ],
        'Sem serifa' => [
            'Roboto' => 'roboto',
            'Montserrat' => 'montserrat',
            'Open Sans' => 'open-sans',
            'Lato' => 'lato',
            'Raleway' => 'raleway',
            'Quicksand' => 'quicksand',
        ],
        'Display' => [
            'Cinzel' => 'cinzel',
            'Crimson Text' => 'crimson-text',
            'Dancing Script' => 'dancing-script',
        ],
    ],

    'google' => [
        'Georgia' => 'Gelasio',
        'Arial' => 'Arimo',
        'Times New Roman' => 'Tinos',
        'Courier New' => 'Cousine',
    ],
];
