<?php

namespace App\Enums;

enum VerifiableField: string
{
    case FIRST_NAME = 'first_name';
    case LAST_NAME_1 = 'last_name_1';
    case LAST_NAME_2 = 'last_name_2';
    case FULL_NAME = 'full_name';
    case CURP = 'curp';
    case RFC = 'rfc';
    case INE_CLAVE = 'ine_clave';
    case INE_OCR = 'ine_ocr';
    case INE_FOLIO = 'ine_folio';
    case BIRTH_DATE = 'birth_date';
    case BIRTH_STATE = 'birth_state';
    case GENDER = 'gender';
    case PHONE = 'phone';
    case EMAIL = 'email';
    case ADDRESS = 'address';
    case ADDRESS_STREET = 'address_street';
    case ADDRESS_NEIGHBORHOOD = 'address_neighborhood';
    case ADDRESS_POSTAL_CODE = 'address_postal_code';
    case ADDRESS_CITY = 'address_city';
    case ADDRESS_STATE = 'address_state';
    case EMPLOYMENT = 'employment';
    case FACE_MATCH = 'face_match';
    case LIVENESS = 'liveness';
    case OFAC_CLEAR = 'ofac_clear';
    case PLD_CLEAR = 'pld_clear';
    case INE_DOCUMENT = 'ine_document';
    case SELFIE = 'selfie';

    /**
     * Get human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::FIRST_NAME => 'Nombre',
            self::LAST_NAME_1 => 'Apellido Paterno',
            self::LAST_NAME_2 => 'Apellido Materno',
            self::FULL_NAME => 'Nombre Completo',
            self::CURP => 'CURP',
            self::RFC => 'RFC',
            self::INE_CLAVE => 'Clave de Elector',
            self::INE_OCR => 'OCR de INE',
            self::INE_FOLIO => 'Folio de INE',
            self::BIRTH_DATE => 'Fecha de Nacimiento',
            self::BIRTH_STATE => 'Estado de Nacimiento',
            self::GENDER => 'Sexo',
            self::PHONE => 'Teléfono',
            self::EMAIL => 'Email',
            self::ADDRESS => 'Domicilio',
            self::ADDRESS_STREET => 'Calle',
            self::ADDRESS_NEIGHBORHOOD => 'Colonia',
            self::ADDRESS_POSTAL_CODE => 'Código Postal',
            self::ADDRESS_CITY => 'Ciudad/Municipio',
            self::ADDRESS_STATE => 'Estado',
            self::EMPLOYMENT => 'Empleo',
            self::FACE_MATCH => 'Reconocimiento Facial',
            self::LIVENESS => 'Prueba de Vida',
            self::OFAC_CLEAR => 'Verificación OFAC',
            self::PLD_CLEAR => 'Verificación PLD',
            self::INE_DOCUMENT => 'Documento INE',
            self::SELFIE => 'Selfie',
        };
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if this is a personal data field.
     */
    public function isPersonalData(): bool
    {
        return in_array($this, [
            self::FIRST_NAME,
            self::LAST_NAME_1,
            self::LAST_NAME_2,
            self::FULL_NAME,
            self::CURP,
            self::RFC,
            self::INE_CLAVE,
            self::INE_OCR,
            self::INE_FOLIO,
            self::BIRTH_DATE,
            self::BIRTH_STATE,
            self::GENDER,
        ]);
    }

    /**
     * Check if this is a contact field.
     */
    public function isContactInfo(): bool
    {
        return in_array($this, [
            self::PHONE,
            self::EMAIL,
        ]);
    }

    /**
     * Check if this is an address field.
     */
    public function isAddressField(): bool
    {
        return in_array($this, [
            self::ADDRESS,
            self::ADDRESS_STREET,
            self::ADDRESS_NEIGHBORHOOD,
            self::ADDRESS_POSTAL_CODE,
            self::ADDRESS_CITY,
            self::ADDRESS_STATE,
        ]);
    }

    /**
     * Check if this is a KYC verification field.
     */
    public function isKycField(): bool
    {
        return in_array($this, [
            self::FACE_MATCH,
            self::LIVENESS,
            self::OFAC_CLEAR,
            self::PLD_CLEAR,
            self::INE_DOCUMENT,
            self::SELFIE,
        ]);
    }
}
