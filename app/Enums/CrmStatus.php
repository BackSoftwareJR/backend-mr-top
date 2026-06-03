<?php

namespace App\Enums;

enum CrmStatus: string
{
    case Nuovo = 'nuovo';
    case Contattato = 'contattato';
    case VisitaFissata = 'visita_fissata';
    case Perso = 'perso';
    case Chiuso = 'chiuso';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
