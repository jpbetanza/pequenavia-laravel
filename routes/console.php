<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Classificação das conversas paradas para o painel de gestão: 05:00 e 17:00, horário de Brasília.
Schedule::command('gestao:classificar')->dailyAt('05:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
Schedule::command('gestao:classificar')->dailyAt('17:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
