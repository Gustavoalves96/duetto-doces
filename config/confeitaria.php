<?php

/*
 * Parâmetros do preço sugerido. Não entram em custo, CMV nem relatório: só
 * na sugestão de preço mostrada no produto e na ficha técnica.
 */
return [

    // gás, energia e o que mais não está na ficha, em % sobre o custo dos insumos
    'indiretos_percentual' => env('CONFEITARIA_INDIRETOS_PERCENTUAL', '10'),

    // margem que se quer ter, em % sobre o preço de venda
    'margem_desejada_percentual' => env('CONFEITARIA_MARGEM_PERCENTUAL', '50'),

];
