<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Páginas em construção ou sem controller específico ainda.
 */
class PlaceholderController extends Controller
{
    private function show(string $pageTitle): void
    {
        $this->render('coming_soon', ['pageTitle' => $pageTitle]);
    }

    public function services(): void      { $this->show('Serviços'); }
    public function professionals(): void { $this->show('Profissionais'); }
    public function units(): void        { $this->show('Unidades'); }
    public function financial(): void    { $this->show('Financeiro'); }
    public function reports(): void      { $this->show('Relatórios'); }
    public function settings(): void     { $this->show('Configurações'); }
}
