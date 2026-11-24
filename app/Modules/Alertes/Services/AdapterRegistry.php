<?php

namespace App\Modules\Alertes\Services;

use App\Modules\Alertes\Contracts\NotificationAdapter;
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Discovers every NotificationAdapter in app/Modules/Alertes/Adapters (drop a file, it is registered)
 * plus classes listed in config('phare.notification_adapters') for third-party packages.
 */
#[Singleton]
class AdapterRegistry
{
    private const RESERVED = ['name', 'type', 'enabled'];

    /** @var array<string, class-string<NotificationAdapter>>|null */
    private ?array $adapters = null;

    /**
     * @return array<string, class-string<NotificationAdapter>> key => class
     */
    public function all(): array
    {
        if ($this->adapters !== null) {
            return $this->adapters;
        }

        $classes = (array) config('phare.notification_adapters', []);

        foreach (Finder::create()->files()->in(__DIR__.'/../Adapters')->name('*.php') as $file) {
            $classes[] = 'App\\Modules\\Alertes\\Adapters\\'.$file->getBasename('.php');
        }

        $adapters = [];

        foreach ($classes as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, NotificationAdapter::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            foreach ($class::fields() as $field) {
                if (in_array($field->name, self::RESERVED, true)) {
                    throw new InvalidArgumentException("Adapter {$class}: field name \"{$field->name}\" is reserved.");
                }
            }

            $adapters[$class::key()] = $class;
        }

        ksort($adapters);

        return $this->adapters = $adapters;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * @return class-string<NotificationAdapter>
     */
    public function class(string $key): string
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown notification adapter \"{$key}\".");
    }

    public function make(string $key): NotificationAdapter
    {
        return app($this->class($key));
    }

    /**
     * Definition list for the UI.
     *
     * @return array<int, array{key: string, label: string, fields: array<int, array<string, mixed>>}>
     */
    public function definitions(): array
    {
        return array_values(array_map(fn (string $class) => [
            'key' => $class::key(),
            'label' => __($class::label()),
            'fields' => array_map(fn ($f) => $f->toArray(), $class::fields()),
        ], $this->all()));
    }
}
