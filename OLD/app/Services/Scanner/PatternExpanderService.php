<?php

declare(strict_types=1);

namespace App\Services\Scanner;

final class PatternExpanderService
{
    /**
     * @return array<int, string>
     */
    public function expandRandomized(string $input, int $limit = 100): array
    {
        $values = $this->expand($input);
        shuffle($values);

        return array_slice($values, 0, max(1, $limit));
    }

    public function count(string $input): int
    {
        return count($this->expand($input));
    }

    /**
     * @return array<int, string>
     */
    private function expand(string $input): array
    {
        $blocks = [];
        $literal = '';
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if ($char !== '[') {
                $literal .= $char;
                continue;
            }

            $closing = strpos($input, ']', $i);
            if ($closing === false) {
                $literal .= $char;
                continue;
            }

            if ($literal !== '') {
                $blocks[] = ['type' => 'literal', 'value' => $literal];
                $literal = '';
            }

            $charset = $this->parseCharset(substr($input, $i + 1, $closing - $i - 1));
            $lens = [1];
            $i = $closing;

            if (($input[$i + 1] ?? null) === '{') {
                $end = strpos($input, '}', $i + 1);
                if ($end !== false) {
                    $lens = $this->parseLens(substr($input, $i + 2, $end - $i - 2));
                    $i = $end;
                }
            }

            $blocks[] = ['type' => 'pattern', 'charset' => $charset, 'lens' => $lens];
        }

        if ($literal !== '') {
            $blocks[] = ['type' => 'literal', 'value' => $literal];
        }

        return $this->expandBlocks($blocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, string>
     */
    private function expandBlocks(array $blocks, int $index = 0): array
    {
        if (!isset($blocks[$index])) {
            return [''];
        }

        $block = $blocks[$index];
        $suffixes = $this->expandBlocks($blocks, $index + 1);

        if ($block['type'] === 'literal') {
            return array_map(static fn (string $suffix): string => $block['value'] . $suffix, $suffixes);
        }

        $values = [];
        foreach ($this->expandPatternBlock($block['charset'], $block['lens']) as $middle) {
            foreach ($suffixes as $suffix) {
                $values[] = $middle . $suffix;
            }
        }

        return $values;
    }

    /**
     * @param array<int, string> $charset
     * @param array<int, int> $lens
     * @return array<int, string>
     */
    private function expandPatternBlock(array $charset, array $lens): array
    {
        $results = [];
        foreach ($lens as $len) {
            $results = [...$results, ...$this->combinations($charset, $len)];
        }

        return $results;
    }

    /**
     * @param array<int, string> $charset
     * @return array<int, string>
     */
    private function combinations(array $charset, int $len): array
    {
        if ($len === 0) {
            return [''];
        }

        $results = [];
        foreach ($charset as $char) {
            foreach ($this->combinations($charset, $len - 1) as $suffix) {
                $results[] = $char . $suffix;
            }
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    private function parseCharset(string $raw): array
    {
        $chars = [];
        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];
            if (($raw[$i + 1] ?? null) === '-' && isset($raw[$i + 2])) {
                $end = $raw[$i + 2];
                for ($ord = ord($char); $ord <= ord($end); $ord++) {
                    $chars[] = chr($ord);
                }
                $i += 2;
                continue;
            }

            $chars[] = $char;
        }

        return array_values(array_unique($chars));
    }

    /**
     * @return array<int, int>
     */
    private function parseLens(string $raw): array
    {
        $parts = preg_split('/;/', $raw) ?: [];
        $lens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (str_contains($part, '-')) {
                [$start, $end] = array_map('intval', explode('-', $part, 2));
                for ($value = $start; $value <= $end; $value++) {
                    $lens[] = $value;
                }
                continue;
            }

            $lens[] = (int) $part;
        }

        return $lens === [] ? [1] : array_values(array_unique($lens));
    }
}
