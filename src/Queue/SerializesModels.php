<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Queue;

use Arifnd\LiteQueue\Queue\Attributes\WithoutRelations;
use ReflectionClass;
use ReflectionProperty;

trait SerializesModels
{
    use SerializesAndRestoresModelIdentifiers;

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $values = [];

        $reflectionClass = new ReflectionClass($this);
        $class = get_class($this);
        $classLevelWithoutRelations = ! empty($reflectionClass->getAttributes(WithoutRelations::class));

        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->isStatic() || ! $property->isInitialized($this)) {
                continue;
            }

            if (method_exists($property, 'isVirtual') && $property->isVirtual()) {
                continue;
            }

            $value = $this->getPropertyValue($property);

            if ($property->hasDefaultValue() && $value === $property->getDefaultValue()) {
                continue;
            }

            $name = $property->getName();

            if ($property->isPrivate()) {
                $name = "\0{$class}\0{$name}";
            } elseif ($property->isProtected()) {
                $name = "\0*\0{$name}";
            }

            $values[$name] = $this->getSerializedPropertyValue(
                $value,
                ! $classLevelWithoutRelations && empty($property->getAttributes(WithoutRelations::class))
            );
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function __unserialize(array $values): void
    {
        $class = get_class($this);

        foreach ((new ReflectionClass($this))->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            if ($property->isPrivate()) {
                $name = "\0{$class}\0{$name}";
            } elseif ($property->isProtected()) {
                $name = "\0*\0{$name}";
            }

            if (! array_key_exists($name, $values)) {
                continue;
            }

            $property->setValue($this, $this->getRestoredPropertyValue($values[$name]));
        }
    }

    protected function getPropertyValue(ReflectionProperty $property): mixed
    {
        return $property->getValue($this);
    }
}
