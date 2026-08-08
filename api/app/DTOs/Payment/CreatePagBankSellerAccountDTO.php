<?php

namespace App\DTOs\Payment;

use App\Support\BrazilDocument;

/**
 * Entrada normalizada para PagBankAccountService::createSellerAccount
 * (roadmap R2.2). CPF/CNPJ e telefones chegam sem máscara — normalização
 * feita aqui, nunca no Service, seguindo o padrão de DTO do projeto.
 */
class CreatePagBankSellerAccountDTO
{
    /**
     * @param  array{street:string,number:string,complement:?string,locality:string,city:string,region_code:string,postal_code:string,country:string}  $personAddress
     * @param  array{country:string,area:string,number:string}  $personPhone
     * @param  array{street:string,number:string,complement:?string,locality:string,city:string,region_code:string,postal_code:string,country:string}|null  $companyAddress
     * @param  array{country:string,area:string,number:string}|null  $companyPhone
     */
    public function __construct(
        public readonly string $personType,
        public readonly string $email,
        public readonly string $personName,
        public readonly string $personBirthDate,
        public readonly string $personMotherName,
        public readonly string $personTaxId,
        public readonly array $personAddress,
        public readonly array $personPhone,
        public readonly ?string $companyName,
        public readonly ?string $companyTaxId,
        public readonly ?array $companyAddress,
        public readonly ?array $companyPhone,
        public readonly \DateTimeInterface $termsAcceptedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        $isPj = ($data['person_type'] ?? null) === 'pj';

        return new self(
            personType: $data['person_type'],
            email: strtolower(trim($data['email'])),
            personName: trim($data['person']['name']),
            personBirthDate: $data['person']['birth_date'],
            personMotherName: trim($data['person']['mother_name']),
            personTaxId: (string) BrazilDocument::normalizeCpf($data['person']['tax_id']),
            personAddress: self::normalizeAddress($data['person']['address']),
            personPhone: self::normalizePhone($data['person']['phone']),
            companyName: $isPj ? trim($data['company']['name']) : null,
            companyTaxId: $isPj ? (string) BrazilDocument::normalizeCnpj($data['company']['tax_id']) : null,
            companyAddress: $isPj ? self::normalizeAddress($data['company']['address']) : null,
            companyPhone: $isPj ? self::normalizePhone($data['company']['phone']) : null,
            termsAcceptedAt: new \DateTimeImmutable($data['terms_accepted_at']),
        );
    }

    /**
     * O documento "principal" da conta: CNPJ para PJ (a empresa é a
     * titular), CPF para PF — usado por PagBankAccountService para
     * persistir document_encrypted e pela resposta mascarada.
     */
    public function primaryDocument(): string
    {
        return $this->personType === 'pj' ? (string) $this->companyTaxId : $this->personTaxId;
    }

    private static function normalizeAddress(array $address): array
    {
        return [
            'street' => trim($address['street']),
            'number' => trim($address['number']),
            'complement' => isset($address['complement']) && $address['complement'] !== ''
                ? trim($address['complement'])
                : null,
            'locality' => trim($address['locality']),
            'city' => trim($address['city']),
            'region_code' => strtoupper(trim($address['region_code'])),
            'postal_code' => (string) preg_replace('/\D/', '', $address['postal_code']),
            'country' => strtoupper(trim($address['country'] ?? 'BRA')),
        ];
    }

    private static function normalizePhone(array $phone): array
    {
        return [
            'country' => (string) preg_replace('/\D/', '', $phone['country']),
            'area' => (string) preg_replace('/\D/', '', $phone['area']),
            'number' => (string) preg_replace('/\D/', '', $phone['number']),
        ];
    }
}
