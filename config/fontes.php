<?php

/*
| Catálogo de fontes dos campos do certificado.
|
| Cada família exibida no editor aponta para a pasta de arquivos .ttf em resources/fonts/
| (os arquivos entram junto com o gerador de imagem). As fontes de "Sistema" usam equivalentes
| livres com as mesmas medidas, porque as originais da Microsoft não existem no servidor.
*/

return [
    'grupos' => [
        'Sistema' => [
            'Georgia' => 'gelasio',
            'Arial' => 'liberation-sans',
            'Times New Roman' => 'liberation-serif',
            'Courier New' => 'liberation-mono',
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
];
