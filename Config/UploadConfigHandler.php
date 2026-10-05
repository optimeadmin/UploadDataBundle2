<?php
/**
 * 26/09/14
 * upload
 */

namespace Manuel\Bundle\UploadDataBundle\Config;

use Doctrine\ORM\EntityManagerInterface;
use Manuel\Bundle\UploadDataBundle\Data\Reader\ReaderLoader;
use Manuel\Bundle\UploadDataBundle\Data\UploadedFileHelperInterface;
use Manuel\Bundle\UploadDataBundle\Data\UploadedItemBulkInsert;
use Manuel\Bundle\UploadDataBundle\Entity\Upload;
use Manuel\Bundle\UploadDataBundle\Entity\UploadAction;
use Manuel\Bundle\UploadDataBundle\Entity\UploadedItem;
use Manuel\Bundle\UploadDataBundle\Exception\UploadProcessException;
use Manuel\Bundle\UploadDataBundle\Profiler\ExceptionProfiler;
use Manuel\Bundle\UploadDataBundle\Profiler\UploadPerformance;
use Manuel\Bundle\UploadDataBundle\Validator\ColumnError;
use Manuel\Bundle\UploadDataBundle\Validator\GroupedConstraintViolations;
use Manuel\Bundle\UploadDataBundle\Validator\UploadedItemValidator;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Throwable;
use function count;
use function md5;
use function sprintf;
use function uniqid;

/**
 * @autor Manuel Aguirre <programador.manuel@gmail.com>
 */
class UploadConfigHandler
{
    public function __construct(
        protected EntityManagerInterface $objectManager,
        private ReaderLoader $readerLoader,
        private UploadedItemValidator $validator,
        private UploadedFileHelperInterface $uploadedFileHelper,
        private ExceptionProfiler $exceptionProfiler,
        private string $uploadDir,
        private UploadPerformance $performance,
        private UploadedItemBulkInsert $bulkInsert,
    ) {
    }

    public function processUpload(
        ResolvedUploadConfig $resolvedConfig,
        UploadedFile $file,
        array $formData = [],
        array $attributes = [],
    ): Upload {
        $config = $resolvedConfig->getConfig();

        try {
            $upload = $config->getInstance();

            $upload->setFilename($file->getClientOriginalName());
            $upload->setConfigClass($config::class);
            $upload->setAttributes($attributes);

            $this->objectManager->beginTransaction();

            if ($config instanceof ConfigUploadFiltersAwareInterface) {
                $config->onPreUpload($upload, $file, $formData);
            }

            $this->objectManager->persist($upload);
            $this->objectManager->flush();

            $newFilename = $this->createUniqueFilename($file, $upload);
            $filename = $this->uploadedFileHelper->saveFile($file, $this->uploadDir, $newFilename);

            $upload->setFile(basename($filename));
            $upload->setFullFilename($filename);

            if ($config instanceof ConfigUploadFiltersAwareInterface) {
                $config->onPostUpload($upload, $filename, $formData);
            }

            $this->objectManager->persist($upload);
            $this->objectManager->flush();
            $this->objectManager->commit();

            return $upload;
        } catch (\Exception $e) {
            $this->profileException($e);

            throw new UploadProcessException($e, 'upload');
        }
    }

    public function processRead(ResolvedUploadConfig $resolvedConfig, Upload $upload): bool
    {
        $config = $resolvedConfig->getConfig();

        if (!$config->isActionable($upload, 'read')) {
            return false;
        }

        $action = $upload->getAction('read');

        try {
            $this->setInProcessAction($action);

            if ($config instanceof ConfigReadFiltersAwareInterface) {
                $config->onPreRead($upload);
            }

            $reader = $this->readerLoader->get($upload);
            $data = $reader->getData($upload);

            $columnsMapper = $resolvedConfig->getConfigColumns()->getColumns();
            $hasConditionalRowFilter = $config instanceof ConditionalRowInterface;
            $delivered = 0;
            $total = 0;

            $this->performance->checkpoint($this->performanceName($config, 'read.before'), $upload->getId());
            $read = $this->performance->start($this->performanceName($config, 'read'));

            try {
                foreach ($data as $dataRowNumber => $item) {
                    ++$delivered;
                    $formattedItemData = [];

                    foreach ($item as $colName => $value) {
                        if (isset($columnsMapper[$colName])) {
                            if (is_array($value)) {
                                $withFormat = $value['with_format'];
                                $withoutFormat = $value['without_format'];
                            } else {
                                $withFormat = $value;
                                $withoutFormat = $value;
                            }

                            $formattedItemData[$colName] = $this->normalizeColumnValue(
                                $columnsMapper[$colName],
                                $withFormat,
                                $withoutFormat,
                            );
                        }
                    }

                    if ($hasConditionalRowFilter && !$config->processRow($formattedItemData, $dataRowNumber)) {
                        continue;
                    }

                    if ($config instanceof RawUploadConfig) {
                        $this->bulkInsert->add((int) $upload->getId(), $dataRowNumber, $formattedItemData);
                    } else {
                        $uploadedItem = $upload->addItem($formattedItemData, $dataRowNumber);
                        $this->objectManager->persist($uploadedItem);
                    }
                    $total++;
                }

                if ($config instanceof RawUploadConfig) {
                    $this->bulkInsert->flushPending();
                }
            } finally {
                $this->performance->stop($read, $upload->getId(), ['rows' => $delivered]);
            }

            $upload->setTotal($total);

            if ($hasConditionalRowFilter) {
                $upload->setAttributeValue('__real_total__', $delivered);
                $upload->setAttributeValue('__excluded_count__', $delivered - $total);
            }

            $persisted = $this->performance->start($this->performanceName($config, 'read.persisted'));

            try {
                $this->completeAction($upload, $action);
            } finally {
                $this->performance->stop($persisted, $upload->getId(), ['rows' => $total]);
            }

            if ($config instanceof ConfigReadFiltersAwareInterface) {
                $config->onPostRead($upload);
            }
        } catch (\Exception $e) {
            if ($config instanceof RawUploadConfig && null !== $upload->getId()) {
                $this->bulkInsert->deleteByUpload((int) $upload->getId());
            }

            $this->onActionException($e, $action, $upload);
        }

        return true;
    }

    public function processValidation(
        ResolvedUploadConfig $resolvedConfig,
        Upload $upload,
        $onlyInvalids = false,
    ): bool {
        $config = $resolvedConfig->getConfig();

        if (!$config->isActionable($upload, 'validate')) {
            return false;
        }

        $action = $upload->getAction('validate');
        $isActionCompleted = $action->isComplete();

        try {
            $validationGroup = $isActionCompleted ? 'upload-revalidate' : 'upload-validate';
            $this->setInProcessAction($action);

            if ($config instanceof ConfigValidateFiltersAwareInterface) {
                $config->onPreValidate($upload);
            }

            $validations = $resolvedConfig->getConfigColumns()->getValidations();
            $valid = $invalids = 0;
            $onlyInvalids = $isActionCompleted && $onlyInvalids;

            if ($onlyInvalids) {
                $valid = $upload->getValids();
            }

            /** @var UploadedItem $item */
            $this->performance->checkpoint($this->performanceName($config, 'validate.before'), $upload->getId());
            $validate = $this->performance->start($this->performanceName($config, 'validate'));

            try {
                if ($config instanceof RawUploadConfig) {
                    $this->validateRawPages(
                        $config,
                        $upload,
                        $validations,
                        $validationGroup,
                        $valid,
                        $invalids,
                        $onlyInvalids,
                    );
                } else {
                    $items = $upload->getItems();

                    if ($onlyInvalids) {
                        $items = $items->filter(function (UploadedItem $item) use ($config) {
                            return !$config->isAlreadyProcessedItemValid($item);
                        });
                    }

                    foreach ($items as $item) {
                        if ($config->itsAnExcludedItem($item)) {
                            $item->setValid(true);
                            ++$valid;

                            $this->objectManager->persist($item);
                            continue;
                        }

                        $violations = new GroupedConstraintViolations();
                        $data = $item->getData();
                        foreach ($validations as $group => $columnValidations) {
                            $context = $this->validator->createValidationContext($item);
                            foreach ($columnValidations as $column => $constraints) {
                                $value = array_key_exists($column, $data) ? $data[$column] : null;
                                $context->atPath($column)->validate($value, $constraints, array('Default', $validationGroup));
                            }

                            // por cada categoria|grupo de validaciones, toca saber si hubieron errores.
                            $violations->addAll($group, $context->getViolations());
                        }

                        if ($violations->hasViolationsForGroup('default')) {
                            // Si hay errores en el grupo por defecto, lo marcamos en el item.
                            $item->setHasDefaultErrors();
                            // esto con la finalidad de poder obviar validaciones propias, cuando las
                            // validaciones mínimas no fueron superadas.
                        }

                        // iniciamos un nuevo contexto para las validaciones propias.
                        $context = $this->validator->createValidationContext($item);
                        $config->validateItem($item, $context, $upload);

                        $this->mergeViolations($violations, $context);

                        $item->setErrors($violations);
                        $item->setValid($config->shouldItemCanBeConsideredAsValid($violations, $item));
                        if ($item->getValid()) {
                            ++$valid;
                        } else {
                            ++$invalids;
                        }

                        $this->objectManager->persist($item);
                    }
                }
            } finally {
                $this->performance->stop($validate, $upload->getId());
            }

            $upload->setValids($valid);
            $upload->setInvalids($invalids);
            $this->completeAction($upload, $action);

            if ($config instanceof ConfigValidateFiltersAwareInterface) {
                $config->onPostValidate($upload);
            }
        } catch (\Exception $e) {
            $this->onActionException($e, $action, $upload);
        }

        return true;
    }

    public function processTransfer(ResolvedUploadConfig $resolvedConfig, Upload $upload): bool
    {
        $config = $resolvedConfig->getConfig();

        if (!$config->isActionable($upload, 'transfer')) {
            return false;
        }

        $action = $upload->getAction('transfer');

        try {
            $this->setInProcessAction($action);

            $this->performance->checkpoint($this->performanceName($config, 'transfer.before'), $upload->getId());
            $transfer = $this->performance->start($this->performanceName($config, 'transfer'));

            try {
                if ($config instanceof RawUploadConfig) {
                    $config->transferRaw($upload, $this->bulkInsert->iterate((int) $upload->getId(), true));
                } else {
                    $config->transfer($upload);
                }
            } finally {
                $this->performance->stop($transfer, $upload->getId());
            }

            $this->completeAction($upload, $action);
        } catch (\Exception $e) {
            $this->onActionException($e, $action, $upload);
        }

        return true;
    }

    public function processDelete(ResolvedUploadConfig $resolvedConfig, Upload $upload): bool
    {
        $config = $resolvedConfig->getConfig();

        if (!$config->isActionable($upload, 'delete')) {
            return false;
        }

        $action = $upload->getAction('delete');

        try {
            $this->setInProcessAction($action);

            if ($config instanceof ConfigDeleteFiltersAwareInterface) {
                $config->onPreDelete($upload);
            }

            $config->delete($upload);
            $this->completeAction($upload, $action);

            if ($config instanceof ConfigDeleteFiltersAwareInterface) {
                $config->onPostDelete($upload);
            }
        } catch (\Exception $e) {
            $this->onActionException($e, $action, $upload);
        }

        return true;
    }

    public function processActionByName(ResolvedUploadConfig $resolvedConfig, Upload $upload, string $name): bool
    {
        $config = $resolvedConfig->getConfig();

        if (!$config->isActionable($upload, $name)) {
            return false;
        }

        $action = $upload->getAction($name);

        try {
            $this->setInProcessAction($action);

            $config->processAction($upload, $action);

            $this->completeAction($upload, $action);
        } catch (\Exception $e) {
            $this->onActionException($e, $action, $upload);
        }

        return true;
    }

    private function setInProcessAction(UploadAction $action): void
    {
        $action->setInProgress();
        $this->objectManager->persist($action);
        $this->objectManager->flush();
    }

    private function completeAction(Upload $upload, UploadAction $action): void
    {
        if ($this->objectManager->contains($upload)) {
            $action->setComplete();
            $this->objectManager->persist($upload);
            $this->objectManager->persist($action);
        }

        $this->objectManager->flush();
    }

    private function normalizeColumnValue(array $column, mixed $withFormat, mixed $withoutFormat): mixed
    {
        $valueType = $column['value_type'] ?? null;
        $formatter = $column['formatter'];

        if ($valueType === 'date' || $valueType === 'datetime') {
            $date = $this->toDateTime($withFormat) ?? $this->toDateTime($withoutFormat);
            $result = $formatter($date, $date ?? $withoutFormat);

            if ($result instanceof DateTimeInterface) {
                return DateTimeImmutable::createFromInterface($result)->format(
                    $valueType === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s'
                );
            }

            return $result;
        }

        if ($withFormat instanceof DateTimeInterface) {
            $withFormat = DateTimeImmutable::createFromInterface($withFormat)->format('Y-m-d H:i:s');
        }

        if ($withoutFormat instanceof DateTimeInterface) {
            $withoutFormat = DateTimeImmutable::createFromInterface($withoutFormat)->format('Y-m-d H:i:s');
        }

        $result = $formatter($withFormat, $withoutFormat);

        if ($result instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($result)->format('Y-m-d H:i:s');
        }

        return $result;
    }

    private function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return null;
    }

    private function createUniqueFilename(UploadedFile $file, Upload $upload): string
    {
        return sprintf(
            '%d_%s_%s.%s',
            $upload->getId(),
            $upload->getUploadedAt()->format('Ymd_his'),
            md5(uniqid($upload->getId() . $file->getClientOriginalName())),
            $file->getClientOriginalExtension()
        );
    }

    protected function profileException(Throwable $e)
    {
        $this->exceptionProfiler->addException($e);
    }

    private function onActionException(Throwable $exception, UploadAction $action, Upload $upload): void
    {
        try {
            if ($this->objectManager->isOpen()) {
                $action->setNotComplete();
                $this->objectManager->persist($upload);
                $this->objectManager->flush();
            }
        } catch (\Exception $e) {
        }

        $this->profileException($exception);

        throw new UploadProcessException($exception, $action->getName());
    }

    private function performanceName(UploadConfig $config, string $name): string
    {
        $prefix = $config instanceof RawUploadConfig ? 'raw_upload_data' : 'upload_data';

        return $prefix.':'.$name;
    }

    private function validateRawPages(
        RawUploadConfig $config,
        Upload $upload,
        array $validations,
        string $validationGroup,
        int &$valid,
        int &$invalids,
        bool $onlyInvalids,
    ): void {
        foreach ($this->bulkInsert->pages((int) $upload->getId(), $onlyInvalids ? false : null) as $page) {
            foreach ($page as $item) {
                $violations = new GroupedConstraintViolations();
                $data = $item->getData() ?? [];

                foreach ($validations as $group => $columnValidations) {
                    $context = $this->validator->createValidationContext($item);
                    foreach ($columnValidations as $column => $constraints) {
                        $value = array_key_exists($column, $data) ? $data[$column] : null;
                        $context->atPath($column)->validate($value, $constraints, ['Default', $validationGroup]);
                    }

                    $violations->addAll($group, $context->getViolations());
                }

                $config->validateRawRow($item, $upload, $violations);

                $item->setErrors($violations);
                $item->setValid(!$violations->hasViolationsForGroup('default'));

                if ($item->getValid()) {
                    ++$valid;
                } else {
                    ++$invalids;
                }
            }

            $this->bulkInsert->updateMarked($page);
        }
    }

    private function mergeViolations(
        GroupedConstraintViolations $violations,
        ContextualValidatorInterface $context,
    ): void {
        foreach ($context->getViolations() as $violation) {
            if ($violation instanceof ColumnError) {
                $violations->addColumnError($violation);
            } else {
                $violations->add('default', $violation);
            }
        }
    }
}
