<?php

namespace AppModelsConcerns;

use IlluminateDatabaseEloquentModel;

trait HasSystemCode
{
    protected static function bootHasSystemCode(): void
    {
        static::created(function (Model $model): void {
            if (! $model->shouldGenerateSystemCode()) {
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
