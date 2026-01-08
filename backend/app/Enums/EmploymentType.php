<?php

namespace App\Enums;

enum EmploymentType: string
{
    case EMPLEADO = 'EMPLEADO';
    case INDEPENDIENTE = 'INDEPENDIENTE';
    case EMPRESARIO = 'EMPRESARIO';
    case PENSIONADO = 'PENSIONADO';
    case ESTUDIANTE = 'ESTUDIANTE';
    case HOGAR = 'HOGAR';
    case DESEMPLEADO = 'DESEMPLEADO';
    case OTRO = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::EMPLEADO => 'Empleado',
            self::INDEPENDIENTE => 'Trabajador Independiente',
            self::EMPRESARIO => 'Empresario',
            self::PENSIONADO => 'Pensionado',
            self::ESTUDIANTE => 'Estudiante',
            self::HOGAR => 'Hogar',
            self::DESEMPLEADO => 'Desempleado',
            self::OTRO => 'Otro',
        };
    }

    /**
     * Check if employment type requires proof of income.
     */
    public function requiresProofOfIncome(): bool
    {
        return in_array($this, [
            self::EMPLEADO,
            self::INDEPENDIENTE,
            self::EMPRESARIO,
            self::PENSIONADO,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
