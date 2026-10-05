<?php

namespace App\Core\CustomFields\Services;

use App\Core\CustomFields\Contracts\CustomFieldServiceInterface;
use App\Core\CustomFields\Models\CustomField;
use App\Core\CustomFields\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CustomFieldService implements CustomFieldServiceInterface
{
    private const TYPES = ['text', 'textarea', 'number', 'date', 'boolean', 'select'];

    public function sync(Model $entity, array $values): void
    {
        $fields = CustomField::query()
            ->where('entity_type', $entity::class)
            ->where('is_active', true)
            ->get()
            ->keyBy('key');

        $errors = [];

        foreach ($fields as $field) {
            $value = Arr::get($values, $field->key);

            if ($field->is_required && ($value === null || $value === '')) {
                $errors['custom_fields.'.$field->key] = 'این فیلد الزامی است.';
                continue;
            }

            if ($value === null || $value === '') {
                CustomFieldValue::query()
                    ->where('custom_field_id', $field->id)
                    ->where('entity_type', $entity::class)
                    ->where('entity_id', $entity->getKey())
                    ->delete();
                continue;
            }

            if (! $this->isValid($field, $value)) {
                $errors['custom_fields.'.$field->key] = 'مقدار واردشده برای این فیلد معتبر نیست.';
                continue;
            }

            CustomFieldValue::updateOrCreate(
                [
                    'custom_field_id' => $field->id,
                    'entity_type' => $entity::class,
                    'entity_id' => $entity->getKey(),
                ],
                ['value' => $this->normalize($field, $value)]
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function values(Model $entity): array
    {
        return CustomFieldValue::query()
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->with('field')
            ->get()
            ->mapWithKeys(fn (CustomFieldValue $value) => [
                $value->field->key => $this->denormalize($value->field, $value->value),
            ])
            ->all();
    }

    private function isValid(CustomField $field, mixed $value): bool
    {
        return match ($field->type) {
            'text', 'textarea' => is_string($value),
            'number' => is_numeric($value),
            'date' => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
            'select' => is_string($value) && in_array($value, $field->options ?? [], true),
            default => false,
        };
    }

    private function normalize(CustomField $field, mixed $value): string
    {
        return match ($field->type) {
            'boolean' => in_array($value, [true, 1, '1', 'true'], true) ? '1' : '0',
            'number' => (string) $value,
            default => (string) $value,
        };
    }

    private function denormalize(CustomField $field, string $value): mixed
    {
        return match ($field->type) {
            'number' => is_numeric($value) && str_contains($value, '.') ? (float) $value : (int) $value,
            'boolean' => $value === '1',
            default => $value,
        };
    }

    public static function supportedEntities(): array
    {
        return [
            \App\Models\User::class => 'کاربر',
            \App\Core\Departments\Models\Department::class => 'واحد سازمانی',
            \App\Core\Departments\Models\Position::class => 'سمت سازمانی',
            \App\Core\Roles\Models\Role::class => 'نقش کاربری',
            \App\Modules\Projects\Models\Project::class => 'پروژه',
            \App\Modules\Tasks\Models\Task::class => 'تسک',
        ];
    }

    public static function isSupportedEntity(string $entityType): bool
    {
        return array_key_exists($entityType, self::supportedEntities());
    }

    public function definitions(string $entityType, bool $activeOnly = true)
    {
        $query = CustomField::query()
            ->where('entity_type', $entityType)
            ->orderBy('sort_order')
            ->orderBy('label');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function generateKey(string $entityType): string
    {
        do {
            $key = 'cf_' . Str::lower(Str::random(16));
        } while (
            CustomField::query()
                ->where('entity_type', $entityType)
                ->where('key', $key)
                ->exists()
        );

        return $key;
    }

    public static function supportedTypes(): array
    {
        return self::TYPES;
    }
}
