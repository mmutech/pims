<?php

namespace App\DTOs\Supplier;

use App\Enums\SupplierStatus;
use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class SupplierData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $legalName = null,
        public ?string $taxIdentificationNumber = null,
        public ?string $registrationNumber = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?string $postalCode = null,
        public ?string $contactPerson = null,
        public SupplierStatus $status = SupplierStatus::Pending,
        public ?string $rating = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $status = Arr::get($data, 'status', SupplierStatus::Pending->value);

        return new self(
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: trim((string) Arr::get($data, 'name')),
            legalName: Arr::get($data, 'legal_name'),
            taxIdentificationNumber: Arr::get($data, 'tax_identification_number'),
            registrationNumber: Arr::get($data, 'registration_number'),
            email: Arr::get($data, 'email'),
            phone: Arr::get($data, 'phone'),
            website: Arr::get($data, 'website'),
            address: Arr::get($data, 'address'),
            city: Arr::get($data, 'city'),
            state: Arr::get($data, 'state'),
            country: Arr::get($data, 'country'),
            postalCode: Arr::get($data, 'postal_code'),
            contactPerson: Arr::get($data, 'contact_person'),
            status: $status instanceof SupplierStatus ? $status : SupplierStatus::from((string) $status),
            rating: Input::stringOrNull(Arr::get($data, 'rating')),
            notes: Arr::get($data, 'notes'),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'tax_identification_number' => $this->taxIdentificationNumber,
            'registration_number' => $this->registrationNumber,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'contact_person' => $this->contactPerson,
            'status' => $this->status->value,
            'rating' => $this->rating,
            'notes' => $this->notes,
        ];
    }
}
