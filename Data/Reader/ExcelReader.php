<?php
/**
 * 30/09/14
 * upload
 */

namespace Manuel\Bundle\UploadDataBundle\Data\Reader;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Manuel\Bundle\UploadDataBundle\Entity\Upload;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @autor Manuel Aguirre <programador.manuel@gmail.com>
 */
class ExcelReader extends BaseReader
{
    private array $extensions = ['xlsx'];
    private ?array $excelHeaders;
    private ?array $columnsMapping;

    public function getData(Upload $upload): array
    {
        $filename = $this->resolveFile($upload->getFullFilename());
        $options = $this->resolveOptions($upload);
        $rowHeadersIndex = (int) $options['row_headers'];

        $this->excelHeaders = $this->readHeaderRow($filename, $rowHeadersIndex);
        $this->columnsMapping = $options['columns_mapping'] ?? [];
        $formattedData = [];

        $this->eachRow($filename, function (int $rowIndex, Row $row) use ($rowHeadersIndex, &$formattedData): void {
            if ($rowIndex <= $rowHeadersIndex || $row->isEmpty()) {
                return;
            }

            $formattedRow = [];

            foreach ($row->getCells() as $columnIndex => $cell) {
                $value = $this->cellValue($cell);
                $this->addValue($formattedRow, (int) $columnIndex, $value, $value);
            }

            $formattedData[$rowIndex] = $formattedRow;
        });

        $this->excelHeaders = null;
        $this->columnsMapping = null;

        return $formattedData;
    }

    public function getHeaders(Upload $upload): array
    {
        $filename = $this->resolveFile($upload->getFullFilename());
        $options = $this->resolveOptions($upload, true);

        return $this->readHeaderRow($filename, (int) $options['row_headers']);
    }

    public function supports(Upload $upload): bool
    {
        return $this->matchExtensions($upload, $this->extensions);
    }

    public function configureOptions(OptionsResolver $resolver, bool $headers = false): void
    {
        parent::configureOptions($resolver, $headers);

        $resolver->setRequired([
            'row_headers',
        ]);
    }

    private function readHeaderRow(string $filename, int $rowHeadersIndex): array
    {
        $headers = [];

        $this->eachRow($filename, function (int $rowIndex, Row $row) use ($rowHeadersIndex, &$headers): void {
            if ($rowIndex !== $rowHeadersIndex) {
                return;
            }

            foreach ($row->getCells() as $columnIndex => $cell) {
                $label = $this->headerLabel($this->cellValue($cell));
                if ($label === null) {
                    continue;
                }

                $headers[$this->columnLetter((int) $columnIndex)] = $label;
            }
        });

        return $headers;
    }

    /**
     * SHOULD_PRESERVE_EMPTY_ROWS hace que key() sea el número real de la fila.
     * Sin eso, OpenSpout devuelve un contador y una cabecera que no está en la
     * fila 1 dejaría de coincidir con el Excel. Las filas vacías se ignoran aquí.
     *
     * SHOULD_USE_1904_DATES lo rellena la librería al abrir el libro, antes de
     * crear el lector de filas. No hay que pisarlo.
     */
    private function eachRow(string $filename, callable $callback): void
    {
        $options = new Options();
        $options->SHOULD_FORMAT_DATES = false;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $reader = new Reader($options);
        $reader->open($filename);

        try {
            foreach ($this->activeSheet($reader)->getRowIterator() as $rowIndex => $row) {
                $callback((int) $rowIndex, $row);
            }
        } finally {
            $reader->close();
        }
    }

    private function activeSheet(Reader $reader): SheetInterface
    {
        $fallback = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->isActive()) {
                return $sheet;
            }

            $fallback ??= $sheet;
        }

        if (null === $fallback) {
            throw new LogicException('El archivo no tiene hojas.');
        }

        return $fallback;
    }

    private function cellValue(Cell $cell): mixed
    {
        if ($cell instanceof FormulaCell) {
            // Sin nodo <v>, OpenSpout entrega 0 en fórmulas numéricas. Un cero
            // cacheado de verdad no se puede distinguir de esa ausencia.
            $value = $cell->getComputedValue();
        } elseif ($cell instanceof ErrorCell) {
            $value = $cell->getRawValue();
        } else {
            $value = $cell->getValue();
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if ($value instanceof DateInterval || $value === null || $value === '') {
            return null;
        }

        return $value;
    }

    private function headerLabel(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function columnLetter(int $zeroBasedIndex): string
    {
        $index = $zeroBasedIndex + 1;
        $letter = '';

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $letter = chr(65 + $modulo).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    private function addValue(
        array &$row,
        int $columnIndex,
        mixed $rawValue,
        mixed $formattedValue
    ): void {
        if (null === $this->excelHeaders || null === $this->columnsMapping) {
            throw new LogicException(
                "No se puede llamar a 'getArrayValue()' sin establecer valores para 'excelHeaders' y 'columnsMapping'"
            );
        }

        $excelColName = $this->columnLetter($columnIndex);

        if (in_array($excelColName, $this->columnsMapping, true)) {
            $configColumnName = array_search($excelColName, $this->columnsMapping, true);

            $row[$configColumnName] = [
                'with_format' => $formattedValue,
                'without_format' => $rawValue,
            ];
        } elseif (isset($this->excelHeaders[$excelColName])) {
            $row[self::EXTRA_FIELDS_NAME][$this->excelHeaders[$excelColName]] = [
                'with_format' => $formattedValue,
                'without_format' => $rawValue,
            ];
        }
    }
}
