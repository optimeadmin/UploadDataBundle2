<?php

namespace Manuel\Bundle\UploadDataBundle\Data;

use ArrayAccess;
use DateTimeImmutable;
use DateTimeInterface;
use Manuel\Bundle\UploadDataBundle\Validator\GroupedConstraintViolations;

/**
 * Fila leída por DBAL. No es una entidad de Doctrine.
 */
class RawUploadedItem implements ArrayAccess
{
    private ?int $id = null;
    private ?int $fileRowNumber = null;
    private ?array $data = [];
    private ?array $extras = null;
    private GroupedConstraintViolations|array|null $errors = null;
    private ?bool $valid = null;
    private bool $markedForUpdate = false;
    private bool $hydrating = false;

    public static function fromRow(array $row): self
    {
        $item = new self();
        $item->hydrating = true;
        $item->id = isset($row['id']) ? (int) $row['id'] : null;
        $item->fileRowNumber = isset($row['file_row_number']) ? (int) $row['file_row_number'] : null;
        $item->data = self::decodeJson($row['data'] ?? null) ?? [];
        $item->extras = self::decodeJson($row['extras'] ?? null);
        $item->errors = self::decodeJson($row['errors'] ?? null);
        $item->valid = self::decodeBool($row['valid'] ?? null);
        $item->hydrating = false;
        $item->markedForUpdate = false;

        return $item;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileRowNumber(): ?int
    {
        return $this->fileRowNumber;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function getDate(string $column): ?DateTimeImmutable
    {
        $value = $this->get($column);

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        foreach (['!Y-m-d', '!Y-m-d H:i:s', DateTimeInterface::ATOM] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        return null;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getExtras(): ?array
    {
        return $this->extras;
    }

    public function setExtra(string $key, mixed $value): void
    {
        $this->extras ??= [];
        $this->extras[$key] = $value;
        $this->touch();
    }

    public function getErrors(): GroupedConstraintViolations
    {
        if ($this->errors instanceof GroupedConstraintViolations) {
            return $this->errors;
        }

        return GroupedConstraintViolations::fromArray($this->errors ?? []);
    }

    public function setErrors(GroupedConstraintViolations|array $errors): void
    {
        $this->errors = $errors;
        $this->touch();
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
        $this->touch();
    }

    public function markForUpdate(): void
    {
        $this->markedForUpdate = true;
    }

    public function isMarkedForUpdate(): bool
    {
        return $this->markedForUpdate;
    }

    public function errorsPayload(): ?array
    {
        if ($this->errors instanceof GroupedConstraintViolations) {
            return $this->errors->getAll();
        }

        return $this->errors;
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_array($this->data) && array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data ??= [];
        $this->data[$offset] = $value;
        $this->touch();
    }

    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            unset($this->data[$offset]);
            $this->touch();
        }
    }

    private function touch(): void
    {
        if (!$this->hydrating) {
            $this->markedForUpdate = true;
        }
    }

    private static function decodeJson(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function decodeBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1') {
            return true;
        }

        if ($value === 0 || $value === '0') {
            return false;
        }

        return null;
    }
}
