<?php

namespace WizardingCode\WebhookOwlery\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WebhookPayloadTransformer
{
    /**
     * Transform a payload according to mapping rules.
     *
     * @param array $payload The original payload
     * @param array $mapping The mapping rules
     *
     * @return array The transformed payload
     */
    final public function transform(array $payload, array $mapping): array
    {
        $result = [];

        foreach ($mapping as $target => $source) {
            // If source is a callable, use it to transform
            if (is_callable($source)) {
                $result[$target] = $source($payload, $target);
                continue;
            }

            // If source is an array, it's a nested transform
            if (is_array($source) && ! isset($source['key']) && ! isset($source['value'])) {
                // Extract nested data
                $nestedData = [];
                foreach ($payload as $item) {
                    if (is_array($item)) {
                        $nestedData[] = $this->transform($item, $source);
                    }
                }
                $result[$target] = $nestedData;
                continue;
            }

            // Handle special mapping configurations
            if (is_array($source)) {
                $result[$target] = $this->processSpecialMapping($payload, $source);
                continue;
            }

            // Simple key mapping
            $result[$target] = Arr::get($payload, $source);
        }

        return $result;
    }

    /**
     * Process special mapping configurations.
     */
    private function processSpecialMapping(array $payload, array $config): mixed
    {
        // Key with optional default value
        if (isset($config['key'])) {
            return Arr::get($payload, $config['key'], $config['default'] ?? null);
        }

        // Static value
        if (array_key_exists('value', $config)) {
            return $config['value'];
        }

        // Concatenation of multiple keys
        if (isset($config['concat'])) {
            $values = [];
            foreach ($config['concat'] as $key) {
                $values[] = Arr::get($payload, $key, '');
            }
            $separator = $config['separator'] ?? ' ';

            return implode($separator, $values);
        }

        // Format a timestamp
        if (isset($config['timestamp'])) {
            $timestamp = Arr::get($payload, $config['timestamp']);
            if (! $timestamp) {
                return null;
            }
            $format = $config['format'] ?? 'Y-m-d H:i:s';

            return date($format, is_numeric($timestamp) ? $timestamp : strtotime($timestamp));
        }

        // Format a date
        if (isset($config['date'])) {
            $date = Arr::get($payload, $config['date']);
            if (! $date) {
                return null;
            }
            $format = $config['format'] ?? 'Y-m-d';

            return date($format, is_numeric($date) ? $date : strtotime($date));
        }

        // Math operation
        if (isset($config['math'])) {
            $operation = $config['math'];
            $value = Arr::get($payload, $config['key'], 0);

            if (isset($config['multiply'])) {
                $value *= $config['multiply'];
            }

            if (isset($config['divide'])) {
                $value /= $config['divide'];
            }

            if (isset($config['add'])) {
                $value += $config['add'];
            }

            if (isset($config['subtract'])) {
                $value -= $config['subtract'];
            }

            if (isset($config['round'])) {
                $decimals = is_numeric($config['round']) ? $config['round'] : 0;
                $value = round($value, $decimals);
            }

            return $value;
        }

        // Boolean conversion
        if (isset($config['boolean'])) {
            $value = Arr::get($payload, $config['boolean']);

            return $this->toBoolean($value);
        }

        // Join array values
        if (isset($config['join'])) {
            $array = Arr::get($payload, $config['join']);
            if (! is_array($array)) {
                return '';
            }
            $separator = $config['separator'] ?? ', ';

            return implode($separator, $array);
        }

        // Map values
        if (isset($config['map'])) {
            $value = Arr::get($payload, $config['key']);

            return $config['map'][$value] ?? ($config['default'] ?? $value);
        }

        // Conditional value
        if (isset($config['if'])) {
            $condition = $this->evaluateCondition($payload, $config['if']);

            return $condition ?
                ($config['then'] ?? true) :
                ($config['else'] ?? false);
        }

        return null;
    }

    /**
     * Evaluate a condition against the payload.
     */
    private function evaluateCondition(array $payload, array $condition): bool
    {
        $key = $condition['key'] ?? null;
        $value = $key ? Arr::get($payload, $key) : null;

        // Check various comparison types
        if (isset($condition['equals'])) {
            return $value == $condition['equals'];
        }

        if (isset($condition['not_equals'])) {
            return $value !== $condition['not_equals'];
        }

        if (isset($condition['greater_than'])) {
            return $value > $condition['greater_than'];
        }

        if (isset($condition['less_than'])) {
            return $value < $condition['less_than'];
        }

        if (isset($condition['contains'])) {
            if (is_string($value)) {
                return Str::contains($value, $condition['contains']);
            }
            if (is_array($value)) {
                return in_array($condition['contains'], $value, true);
            }

            return false;
        }

        if (isset($condition['empty'])) {
            return empty($value) === $condition['empty'];
        }

        if (isset($condition['exists'])) {
            return Arr::has($payload, $key) === $condition['exists'];
        }

        // Complex conditions with AND/OR
        if (isset($condition['and'])) {
            foreach ($condition['and'] as $subCondition) {
                if (! $this->evaluateCondition($payload, $subCondition)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($condition['or'])) {
            foreach ($condition['or'] as $subCondition) {
                if ($this->evaluateCondition($payload, $subCondition)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Convert a value to boolean.
     */
    private function toBoolean(string|bool $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lowerValue = strtolower($value);

            return in_array($lowerValue, ['true', 'yes', 'y', '1', 'on']);
        }

        return (bool) $value;
    }

    /**
     * Filter a payload to include only the specified keys.
     */
    final public function only(array $payload, array $keys): array
    {
        return Arr::only($payload, $keys);
    }

    /**
     * Filter a payload to exclude the specified keys.
     */
    final public function except(array $payload, array $keys): array
    {
        return Arr::except($payload, $keys);
    }

    /**
     * Flatten a nested payload to a single level.
     */
    final public function flatten(array $payload, string $prefix = '', string $separator = '.'): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $newKey = $prefix ? $prefix . $separator . $key : $key;

            if (is_array($value) && ! empty($value)) {
                $result = array_merge($result, $this->flatten($value, $newKey, $separator));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Extract a subset of the payload using a dot notation path.
     */
    final public function extract(array $payload, string $path): mixed
    {
        return Arr::get($payload, $path);
    }

    /**
     * Merge multiple payloads together.
     */
    final public function merge(array ...$payloads): array
    {
        return array_merge(...$payloads);
    }

    /**
     * Convert an Eloquent model to a webhook payload.
     *
     * @param array $only   Only include these attributes
     * @param array $except Exclude these attributes
     * @param array $with   Include these relationships
     */
    final public function fromModel(Model|Collection|array $model, array $only = [], array $except = [], array $with = []): array
    {
        if ($model instanceof Model) {
            // Load relationships if specified
            if (! empty($with)) {
                $model->load($with);
            }

            // Convert to array
            $array = $model->toArray();

            // Apply filters
            if (! empty($only)) {
                $array = Arr::only($array, $only);
            } elseif (! empty($except)) {
                $array = Arr::except($array, $except);
            }

            return $array;
        }

        if ($model instanceof Collection) {
            return $model->map(function ($item) use ($only, $except, $with) {
                return $this->fromModel($item, $only, $except, $with);
            })->toArray();
        }

        if (is_array($model)) {
            return $model;
        }

        return [];
    }
}
