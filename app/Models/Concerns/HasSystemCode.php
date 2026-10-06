<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSystemCode
{
    protected static function bootHasSystemCode(): void
    {
        static::creating(function (Model $model): void {
            if (
                ! $model->shouldGenerateSystemCode()
                || filled($model->getAttribute('code'))
            ) {
                return;
            }

            $model->setAttribute(
                'code',
                '__SYS_'.Str::uuid()->toString(),
            );
        });

        static::created(function (Model $model): void {
            if (
                ! $model->shouldGenerateSystemCode()
                || ! str_starts_with((string) $model->getAttribute('code'), '__SYS_')
            ) {
                return;
            }

            $generatedCode = sprintf(
                '%s-%06d',
                static::systemCodePrefix(),
                (int) $model->getKey(),
            );

            $model->newQuery()
                ->whereKey($model->getKey())
                ->update([
                    'code' => $generatedCode,
                ]);

            $model->setAttribute('code', $generatedCode);
        });
    }

    abstract protected static function systemCodePrefix(): string;

    protected function shouldGenerateSystemCode(): bool
    {
        return true;
    }
}
