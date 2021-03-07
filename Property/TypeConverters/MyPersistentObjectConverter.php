<?php
namespace Mediadreams\MdNewsfrontend\Property\TypeConverters;

use TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException;
use TYPO3\CMS\Extbase\Property\Exception\TargetNotFoundException;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;

class MyPersistentObjectConverter extends PersistentObjectConverter {

    /**
     * @var string
     */
//    protected $targetType = 'Mediadreams\\MdNewsfrontend\\Domain\\Model\\News';

    /**
     * @var int
     */
//    protected $priority = 2;

    /**
     * Fetch an object from persistence layer.
     *
     * @param mixed $identity
     * @param string $targetType
     * @return object
     * @throws InvalidSourceException
     * @throws TargetNotFoundException
     */
    protected function fetchObjectFromPersistence($identity, string $targetType) : object {
        if (ctype_digit((string)$identity)) {
            $query = $this->persistenceManager->createQueryForType($targetType);
            $query->getQuerySettings()->setIgnoreEnableFields(TRUE);
            $constraints = $query->equals('uid', $identity);
            $object = $query->matching($constraints)->execute()->getFirst();
        } else {
            throw new InvalidSourceException('The identity property "' . $identity . '" is no UID.', 1297931020);
        }

        if ($object === NULL) {
            throw new TargetNotFoundException('Object with identity "' . print_r($identity, TRUE) . '" not found.', 1297933823);
        }

        return $object;
    }

}
