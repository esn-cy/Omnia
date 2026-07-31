<?php /** @noinspection PhpUnused */

namespace Drupal\omnia\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\FileInterface;
use Exception;

abstract class EnumBackedEntityBase extends ContentEntityBase implements EnumBackedEntityInterface
{
    /**
     * {@inheritdoc}
     * @noinspection PhpUnreachableStatementInspection
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
    {
        $fields = parent::baseFieldDefinitions($entity_type);

        $enumClass = static::getFieldEnumClass();

        /** @var FieldEnumInterface $field */
        foreach ($enumClass::cases() as $field) {
            $type = $field->type();
            $fields[$field->value] = BaseFieldDefinition::create($type)
                ->setLabel($field->label())
                ->setRequired($field->required());

            if ($field->unique()) {
                $fields[$field->value]->addConstraint('UniqueField', [
                    'message' => "The {$field->label()} must be unique."
                ]);
            }

            if ($field->unlimitedCardinality()) {
                $fields[$field->value]->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
            }

            if (!empty($field->settings())) {
                $fields[$field->value]->setSettings($field->settings());
            }

            if ($field->default() !== null) {
                $fields[$field->value]->setDefaultValue($field->default());
            }
        }

        return $fields;
    }

    /**
     * Returns the Enum class that defines the fields for this entity.
     *
     * @return class-string<FieldEnumInterface>
     * @throws Exception If the child class does not override this method.
     */
    protected static function getFieldEnumClass(): string
    {
        throw new Exception('The entity class ' . static::class . ' must override the static getFieldEnumClass() method.');
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(FieldEnumInterface $field, mixed $value): self
    {
        $this->set($field->value, $value);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setNull(FieldEnumInterface $field): self
    {
        $this->set($field->value, null);
        return $this;
    }

    /**
     * Helper to safely retrieve a DrupalDateTime object from a datetime field.
     *
     * @param FieldEnumInterface $field The field enum case.
     * @return DrupalDateTime|null The parsed datetime, or null if empty or invalid.
     */
    protected function getDateTimeValue(FieldEnumInterface $field): ?DrupalDateTime
    {
        if ($value = $this->getValue($field)) {
            $dateTime = new DrupalDateTime($value);
            if (!$dateTime->hasErrors()) {
                return $dateTime;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(FieldEnumInterface $field): mixed
    {
        $drupalField = $this->get($field->value);

        if ($field->unlimitedCardinality()) {
            $values = [];
            foreach ($drupalField as $item) {
                $values[] = match ($field->type()) {
                    'entity_reference' => $item->target_id,
                    default => $item->value,
                };
            }
            return $values;
        }

        return match ($field->type()) {
            'entity_reference' => $drupalField->target_id,
            default => $drupalField->value,
        };
    }

    /**
     * Helper to safely retrieve a referenced File entity.
     *
     * @param FieldEnumInterface $field The field enum case.
     * @return FileInterface|null The referenced file entity, or null if not found or empty.
     */
    protected function getFile(FieldEnumInterface $field): ?FileInterface
    {
        $item = $this->get($field->value);
        if ($item->isEmpty()) {
            return null;
        }

        $entity = $item->entity;
        return $entity instanceof FileInterface ? $entity : null;
    }
}