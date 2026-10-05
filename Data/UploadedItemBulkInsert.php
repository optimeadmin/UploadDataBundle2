<?php

namespace Manuel\Bundle\UploadDataBundle\Data;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Inserta, actualiza y lee filas de upload_data_uploaded_item sin el ORM.
 */
class UploadedItemBulkInsert
{
    public const BATCH = 500;

    private array $pending = [];

    public function __construct(private Connection $connection)
    {
    }

    public function add(int $uploadId, int $rowNumber, array $data): void
    {
        $this->pending[] = [
            $uploadId,
            $rowNumber,
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];

        if (count($this->pending) >= self::BATCH) {
            $this->flushPending();
        }
    }

    public function flushPending(): void
    {
        if ($this->pending === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        $types = [];

        foreach ($this->pending as $row) {
            $placeholders[] = '(?, ?, ?, NULL, NULL, NULL)';
            array_push($params, $row[0], $row[1], $row[2]);
            array_push($types, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING);
        }

        $sql = 'INSERT INTO upload_data_uploaded_item (upload_id, file_row_number, data, extras, errors, valid) VALUES '
            .implode(', ', $placeholders);

        $this->connection->executeStatement($sql, $params, $types);
        $this->pending = [];
    }

    public function deleteByUpload(int $uploadId): void
    {
        $this->pending = [];
        $this->connection->executeStatement(
            'DELETE FROM upload_data_uploaded_item WHERE upload_id = ?',
            [$uploadId],
            [ParameterType::INTEGER]
        );
    }

    /**
     * @return iterable<int, list<RawUploadedItem>>
     */
    public function pages(int $uploadId, ?bool $validOnly = null): iterable
    {
        $offset = 0;

        do {
            $page = $this->fetchPage($uploadId, $offset, self::BATCH, $validOnly);
            if ($page === []) {
                break;
            }

            yield $page;
            $offset += self::BATCH;
        } while (count($page) === self::BATCH);
    }

    /**
     * @return \Generator<int, RawUploadedItem>
     */
    public function iterate(int $uploadId, ?bool $validOnly = null): \Generator
    {
        foreach ($this->pages($uploadId, $validOnly) as $page) {
            try {
                foreach ($page as $item) {
                    yield $item;
                }
            } finally {
                $this->updateMarked($page);
            }
        }
    }

    /**
     * @param list<RawUploadedItem> $items
     */
    public function updateMarked(array $items): void
    {
        foreach ($items as $item) {
            if (!$item->isMarkedForUpdate() || null === $item->getId()) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE upload_data_uploaded_item SET data = ?, extras = ?, errors = ?, valid = ? WHERE id = ?',
                [
                    json_encode($item->getData() ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    $item->getExtras() === null ? null : json_encode($item->getExtras(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    $item->errorsPayload() === null ? null : json_encode($item->errorsPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    $item->isValid() === null ? null : (int) $item->isValid(),
                    $item->getId(),
                ],
                [
                    ParameterType::STRING,
                    $item->getExtras() === null ? ParameterType::NULL : ParameterType::STRING,
                    $item->errorsPayload() === null ? ParameterType::NULL : ParameterType::STRING,
                    $item->isValid() === null ? ParameterType::NULL : ParameterType::INTEGER,
                    ParameterType::INTEGER,
                ]
            );
        }
    }

    /**
     * @return list<RawUploadedItem>
     */
    private function fetchPage(int $uploadId, int $offset, int $limit, ?bool $validOnly): array
    {
        $sql = 'SELECT id, file_row_number, data, extras, errors, valid
            FROM upload_data_uploaded_item
            WHERE upload_id = ?';
        $params = [$uploadId];
        $types = [ParameterType::INTEGER];

        if ($validOnly !== null) {
            $sql .= ' AND valid = ?';
            $params[] = (int) $validOnly;
            $types[] = ParameterType::INTEGER;
        }

        $sql .= ' ORDER BY file_row_number ASC, id ASC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $types[] = ParameterType::INTEGER;
        $types[] = ParameterType::INTEGER;

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);
        $items = [];

        foreach ($rows as $row) {
            $items[] = RawUploadedItem::fromRow($row);
        }

        return $items;
    }
}
