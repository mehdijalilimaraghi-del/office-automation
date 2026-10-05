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

            $model->updateQuietly([
                'code' => sprintf(
                    '%s-%06d',
                    static::systemCodePrefix(),
                    (int) $model->getKey(),
                ),
            ]);
        });
    }

    abstract protected static function systemCodePrefix(): string;

    protected function shouldGenerateSystemCode(): bool
    {
        return true;
    }
}
