<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Settings\Services\SystemSettingsService;
use InvalidArgumentException;

class CorrespondenceCompositionSettings
{
    public const METHOD_EDITOR = 'editor';
    public const METHOD_WORD = 'word';
    public const METHOD_BOTH = 'both';

    public const APPROVAL_DIRECT = 'direct';
    public const APPROVAL_REQUIRED = 'approval';

    public function __construct(private readonly SystemSettingsService $settings)
    {
    }

    /**
     * @return array{method: string, approval: array<string, string>}
     */
    public function get(): array
    {
        $stored = $this->settings->get([
            'correspondence.composition.method' => self::METHOD_BOTH,
            'correspondence.approval.incoming' => self::APPROVAL_REQUIRED,
            'correspondence.approval.outgoing' => self::APPROVAL_REQUIRED,
            'correspondence.approval.internal' => self::APPROVAL_DIRECT,
        ]);

        $method = (string) $stored['correspondence.composition.method'];
        if (! in_array($method, [self::METHOD_EDITOR, self::METHOD_WORD, self::METHOD_BOTH], true)) {
            $method = self::METHOD_BOTH;
        }

        return [
            'method' => $method,
            'approval' => [
                'incoming' => (string) $stored['correspondence.approval.incoming'],
                'outgoing' => (string) $stored['correspondence.approval.outgoing'],
                'internal' => (string) $stored['correspondence.approval.internal'],
            ],
        ];
    }

    public function requiresApproval(string $type): bool
    {
        if (! in_array($type, ['incoming', 'outgoing', 'internal'], true)) {
            throw new InvalidArgumentException('نوع مکاتبه نامعتبر است.');
        }

        return $this->get()['approval'][$type] === self::APPROVAL_REQUIRED;
    }
}
