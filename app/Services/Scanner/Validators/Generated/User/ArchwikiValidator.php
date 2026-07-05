<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ArchwikiValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'archwiki';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Archwiki';
    }

    public function siteUrl(): string
    {
        return 'https://wiki.archlinux.org/title/User:{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://wiki.archlinux.org/api.php?action=query&format=json&list=users&ususers={$target}&usprop=blockinfo|groups|editcount|registration|gender&formatversion=2";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $users = $response->json('query.users');
        if (!is_array($users) || $users === []) {
            return ['Available', ''];
        }

        $user = $users[0];
        if (!is_array($user)) {
            return ['Error', 'Unexpected user structure'];
        }

        if (($user['missing'] ?? null) === true) {
            return ['Available', ''];
        }

        if (array_key_exists('userid', $user)) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected user structure'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $users = $response->json('query.users');
        if (!is_array($users) || $users === [] || !is_array($users[0] ?? null)) {
            return [];
        }

        $user = $users[0];
        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        if (isset($user['userid']) && is_numeric($user['userid'])) {
            $metadata['archwiki_user_id'] = (int) $user['userid'];
        }
        if (!empty($user['registration'])) {
            $registration = (string) $user['registration'];
            $timestamp = strtotime($registration);
            $metadata['created_at'] = $timestamp !== false
                ? gmdate('Y-m-d\TH:i:s+00:00', $timestamp)
                : $registration;
        }
        if (isset($user['editcount']) && is_numeric($user['editcount'])) {
            $metadata['edit_count'] = (int) $user['editcount'];
        }
        $gender = trim((string) ($user['gender'] ?? ''));
        if ($gender !== '' && $gender !== 'unknown') {
            $metadata['gender'] = $gender;
        }
        $groups = array_values(array_filter(
            array_map('strval', is_array($user['groups'] ?? null) ? $user['groups'] : []),
            static fn (string $group): bool => $group !== '*'
        ));
        if ($groups !== []) {
            $metadata['groups'] = $groups;
        }

        return $metadata;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];

        if (is_int($metadata['archwiki_user_id'] ?? null)) {
            $summary['ID'] = $metadata['archwiki_user_id'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Joined'] = $metadata['created_at'];
        }
        if (is_int($metadata['edit_count'] ?? null)) {
            $summary['EditCount'] = $metadata['edit_count'];
        }
        if (is_string($metadata['gender'] ?? null) && $metadata['gender'] !== '') {
            $summary['Gender'] = $metadata['gender'];
        }
        if (($metadata['groups'] ?? []) !== []) {
            $summary['Groups'] = $metadata['groups'];
        }

        return $this->metadataSummary($summary);
    }
}
