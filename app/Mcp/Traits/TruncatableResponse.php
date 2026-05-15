<?php

namespace App\Mcp\Traits;

trait TruncatableResponse
{
    private const MAX_RESPONSE_BYTES = 32_000;

    /**
     * データ削減型の安全な切り捨て（JSON破壊リスクなし）。
     *
     * 削除優先順位（高→低）:
     * 1. search_trace を削除
     * 2. payloads.structured / payloads.visual を削除
     * 3. payloads.text.lines を削除（text フィールドのみ残す）
     * 4. meta.ledger_defines の column_define を削除（id, name のみ残す）
     * 5. ledgers 配列の末尾件数を削減し __truncated_items__ で削除件数を報告
     */
    protected function truncateIfNeeded(array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (mb_strlen($json, '8bit') <= self::MAX_RESPONSE_BYTES) {
            return $data;
        }

        // Convert to plain arrays to avoid "Indirect modification of overloaded element"
        // errors when iterating Eloquent models or stdClass objects by reference.
        $data = json_decode($json, true);

        $truncatedAt = [];

        // Priority 1: Remove search_trace
        if (isset($data['search_trace'])) {
            unset($data['search_trace']);
            $truncatedAt[] = 'search_trace';
            [$data, $sizeOk] = $this->checkTruncationSize($data);
            if ($sizeOk) {
                return $this->markTruncated($data, $truncatedAt);
            }
        }

        // Priority 2: Remove payloads.structured and payloads.visual
        $data = $this->stripPayloadSections($data, $truncatedAt);
        [$data, $sizeOk] = $this->checkTruncationSize($data);
        if ($sizeOk) {
            return $this->markTruncated($data, $truncatedAt);
        }

        // Priority 3: Remove payloads.text.lines (keep text field only)
        $data = $this->stripPayloadLines($data, $truncatedAt);
        [$data, $sizeOk] = $this->checkTruncationSize($data);
        if ($sizeOk) {
            return $this->markTruncated($data, $truncatedAt);
        }

        // Priority 4: Remove meta.ledger_defines column_define
        $data = $this->stripMetaColumnDefines($data, $truncatedAt);
        [$data, $sizeOk] = $this->checkTruncationSize($data);
        if ($sizeOk) {
            return $this->markTruncated($data, $truncatedAt);
        }

        // Priority 5: Reduce ledgers array tail items
        $data = $this->reduceLedgersTail($data, $truncatedAt);

        return $this->markTruncated($data, $truncatedAt);
    }

    /**
     * @return array{array, bool} [data, sizeUnderLimit]
     */
    private function checkTruncationSize(array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sizeOk = mb_strlen($json, '8bit') <= self::MAX_RESPONSE_BYTES;

        return [$data, $sizeOk];
    }

    /**
     * @param  string[]  $truncatedAt
     */
    private function markTruncated(array $data, array $truncatedAt): array
    {
        $data['__truncated__'] = true;
        if (! empty($truncatedAt)) {
            $data['__truncated_at__'] = array_unique($truncatedAt);
        }

        return $data;
    }

    /**
     * Priority 2: Remove payloads.structured and payloads.visual from all attachments.
     *
     * @param  string[]  $truncatedAt
     */
    private function stripPayloadSections(array $data, array &$truncatedAt): array
    {
        $applied = false;

        if (isset($data['ledgers'])) {
            foreach ($data['ledgers'] as &$ledger) {
                if (isset($ledger['attachments']) && is_array($ledger['attachments'])) {
                    foreach ($ledger['attachments'] as &$att) {
                        if (! is_array($att)) {
                            continue;
                        }
                        if (isset($att['payloads']['structured'])) {
                            unset($att['payloads']['structured']);
                            $applied = true;
                        }
                        if (isset($att['payloads']['visual'])) {
                            unset($att['payloads']['visual']);
                            $applied = true;
                        }
                    }
                }
            }
            unset($ledger, $att);
        }

        if ($applied) {
            $truncatedAt[] = 'payloads.structured';
            $truncatedAt[] = 'payloads.visual';
        }

        return $data;
    }

    /**
     * Priority 3: Remove payloads.text.lines (keep text field only).
     *
     * @param  string[]  $truncatedAt
     */
    private function stripPayloadLines(array $data, array &$truncatedAt): array
    {
        $applied = false;

        if (isset($data['ledgers'])) {
            foreach ($data['ledgers'] as &$ledger) {
                if (isset($ledger['attachments']) && is_array($ledger['attachments'])) {
                    foreach ($ledger['attachments'] as &$att) {
                        if (! is_array($att)) {
                            continue;
                        }
                        if (isset($att['payloads']['text']['lines'])) {
                            unset($att['payloads']['text']['lines']);
                            $applied = true;
                        }
                    }
                }
            }
            unset($ledger, $att);
        }

        if ($applied) {
            $truncatedAt[] = 'payloads.text.lines';
        }

        return $data;
    }

    /**
     * Priority 4: Remove meta.ledger_defines column_define (keep id, name only).
     *
     * @param  string[]  $truncatedAt
     */
    private function stripMetaColumnDefines(array $data, array &$truncatedAt): array
    {
        $applied = false;

        if (isset($data['meta']['ledger_defines']) && is_array($data['meta']['ledger_defines'])) {
            foreach ($data['meta']['ledger_defines'] as $key => &$define) {
                if (is_array($define) && isset($define['column_define'])) {
                    unset($define['column_define']);
                    $applied = true;
                }
            }
            unset($define);
        }

        if ($applied) {
            $truncatedAt[] = 'meta.ledger_defines.column_define';
        }

        return $data;
    }

    /**
     * Priority 5: Reduce ledgers array tail items.
     *
     * @param  string[]  $truncatedAt
     */
    private function reduceLedgersTail(array $data, array &$truncatedAt): array
    {
        if (! isset($data['ledgers']) || ! is_array($data['ledgers'])) {
            return $data;
        }

        $originalCount = count($data['ledgers']);
        if ($originalCount <= 1) {
            return $data;
        }

        // Remove items from the tail one by one until size is under limit
        while (count($data['ledgers']) > 1) {
            array_pop($data['ledgers']);
            [, $sizeOk] = $this->checkTruncationSize($data);
            if ($sizeOk) {
                break;
            }
        }

        $removedCount = $originalCount - count($data['ledgers']);
        if ($removedCount > 0) {
            $data['__truncated_items__'] = $removedCount;
            $truncatedAt[] = 'ledgers (removed '.$removedCount.' items)';
        }

        return $data;
    }
}
