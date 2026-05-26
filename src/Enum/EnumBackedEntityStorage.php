<?php /** @noinspection PhpUnused */

namespace Drupal\esn_cyprus_core\Enum;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class EnumBackedEntityStorage extends SqlContentEntityStorage
{
    public function getByUniqueField(FieldEnumInterface $field, string $value): EnumBackedEntityInterface|null
    {
        $ids = $this->getQuery()
            ->accessCheck(FALSE)
            ->condition($field->value, $value)
            ->range(0, 1)
            ->execute();

        if (empty($ids)) {
            return null;
        }

        $entity = $this->load(reset($ids));
        if (!$entity instanceof EnumBackedEntityInterface) {
            return null;
        }
        return $entity;
    }

    public function countByField(FieldEnumInterface $field, string $value): int
    {
        return $this->getQuery()
            ->accessCheck(FALSE)
            ->condition($field->value, $value)
            ->count()
            ->execute();
    }
}